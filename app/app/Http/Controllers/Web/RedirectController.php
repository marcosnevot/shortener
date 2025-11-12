<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\IngestClickEvent;
use App\Models\Link;
use App\Services\SlugService;
use App\Services\MetricsContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RedirectController extends Controller
{
    public function __invoke(Request $req, string $slug, MetricsContract $metrics)
    {
        $metrics = app(MetricsContract::class);
        $t0 = microtime(true);

        $slugSvc = app(SlugService::class);
        [$idB62, $sig] = $slugSvc->parseSlug($slug);
        if ($idB62 === '' || strlen($sig) !== 11) {
            $metrics->counterInc('redirect_requests_total', ['result' => 'bad_slug']);
            abort(404);
        }

        $id = $slugSvc->base62Decode($idB62);
        $cacheKey = "link:$id";

        $link = Cache::remember($cacheKey, 600, fn() => Link::find($id));
        if (!$link || $link->deleted_at) {
            $metrics->counterInc('redirect_requests_total', ['result' => 'not_found']);
            abort(404);
        }
        if ($link->is_banned) {
            $metrics->counterInc('redirect_requests_total', ['result' => 'banned']);
            abort(404);
        }

        $expected = $slugSvc->sign($link->id, $link->url, config('shortener.hmac_key'));
        if (!hash_equals($expected, $sig)) {
            $metrics->counterInc('redirect_requests_total', ['result' => 'bad_sig']);
            abort(404);
        }

        if ($link->expires_at && now()->gt($link->expires_at)) {
            $metrics->counterInc('redirect_requests_total', ['result' => 'expired']);
            abort(404);
        }

        // HEAD-safe: sólo contamos en GET
        $isHead = strtoupper($req->method()) === 'HEAD';
        if (!$isHead) {
            if ($link->max_clicks) {
                $updated = Link::whereKey($link->id)
                    ->whereColumn('clicks_count', '<', 'max_clicks')
                    ->increment('clicks_count');

                if ($updated === 0) {
                    $metrics->counterInc('redirect_requests_total', ['result' => 'limit_reached']);
                    abort(404);
                }
                $link->clicks_count++;
                Cache::put($cacheKey, $link, 600);
            } else {
                Link::whereKey($link->id)->increment('clicks_count');
                $link->clicks_count++;
                Cache::put($cacheKey, $link, 600);
            }

            IngestClickEvent::dispatch(
                linkId: $link->id,
                ts: now()->toImmutable(),
                referrer: $req->headers->get('referer'),
                ua: $req->userAgent() ?? '',
                ip: $req->ip()
            )->onQueue('analytics');
        }

        // Métricas OK + latencia
        $metrics->counterInc('redirect_requests_total', ['result' => 'ok']);
        $metrics->histogramObserve('redirect_duration_seconds', microtime(true) - $t0, ['result' => 'ok']);

        return redirect()->away($link->url, 302, [
            'Cache-Control'   => 'no-store',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ]);
    }
}
