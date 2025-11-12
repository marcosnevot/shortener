<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Link;
use App\Services\SlugService;
use App\Services\UrlNormalizer;
use App\Services\MetricsContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class LinkController extends Controller
{
    public function store(Request $request, UrlNormalizer $norm, SlugService $slugger, MetricsContract $metrics)
    {
        try {
            $data = $request->validate([
                'url'            => ['required','string','max:2048'],
                'expires_at'     => ['nullable','date','after:now'],
                'max_clicks'     => ['nullable','integer','min:1'],
                'domain_scope'   => ['nullable','array'],
                'domain_scope.*' => ['string']
            ]);

            try {
                $url = $norm->normalize($data['url']);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages(['url' => $e->getMessage()]);
            }

            $domain = $norm->domain($url);
            $global = config('shortener.domain_whitelist', []);
            if (!empty($global) && !in_array($domain, $global, true)) {
                throw ValidationException::withMessages(['url' => 'Domain not allowed by whitelist']);
            }
            if (!empty($data['domain_scope'] ?? []) && !in_array($domain, $data['domain_scope'], true)) {
                throw ValidationException::withMessages(['domain_scope' => 'Must include the target domain']);
            }

            $placeholder = 'tmp_'.bin2hex(random_bytes(8));
            $link = new Link();
            $link->slug         = $placeholder;
            $link->id_b62       = $placeholder;
            $link->sig          = substr(hash('sha256', $placeholder), 0, 11);
            $link->url          = $url;
            $link->expires_at   = $data['expires_at'] ?? null;
            $link->max_clicks   = $data['max_clicks'] ?? null;
            $link->domain_scope = $data['domain_scope'] ?? null;
            $link->is_banned    = false;
            $link->save();

            [$slug, $idB62, $sig] = $slugger->makeSlug(
                $link->id, $link->url, (string) config('shortener.hmac_key')
            );
            $link->slug = $slug; $link->id_b62 = $idB62; $link->sig = $sig; $link->save();

            Cache::put("link:{$link->id}", $link, now()->addMinutes(10));

            $metrics->counterInc('link_create_total', ['result' => 'created']);

            return response()->json([
                'id'         => $link->id,
                'slug'       => $link->slug,
                'short_url'  => url("/r/{$link->slug}"),
                'url'        => $link->url,
                'expires_at' => optional($link->expires_at)?->toIso8601String(),
                'max_clicks' => $link->max_clicks,
            ], 201);
        } catch (ValidationException $e) {
            $metrics->counterInc('link_create_total', ['result' => 'invalid']);
            throw $e;
        } catch (\Throwable $e) {
            $metrics->counterInc('link_create_total', ['result' => 'error']);
            throw $e;
        }
    }

    public function show(int $id, MetricsContract $metrics)
    {
        $link = Link::find($id);
        if (!$link) {
            $metrics->counterInc('link_show_total', ['result' => 'not_found']);
            abort(404);
        }
        $metrics->counterInc('link_show_total', ['result' => 'ok']);

        return response()->json([
            'id'         => $link->id,
            'slug'       => $link->slug,
            'short_url'  => url("/r/{$link->slug}"),
            'url'        => $link->url,
            'expires_at' => optional($link->expires_at)?->toIso8601String(),
            'max_clicks' => $link->max_clicks,
            'clicks'     => $link->clicks_count,
            'is_banned'  => $link->is_banned,
            'deleted_at' => optional($link->deleted_at)?->toIso8601String(),
        ]);
    }

    public function destroy(int $id, MetricsContract $metrics)
    {
        $link = Link::find($id);
        if (!$link) {
            $metrics->counterInc('link_delete_total', ['result' => 'not_found']);
            abort(404);
        }
        $link->delete();
        Cache::forget("link:$id");
        $metrics->counterInc('link_delete_total', ['result' => 'ok']);
        return response()->noContent();
    }

    public function ban(int $id, MetricsContract $metrics)
    {
        $link = Link::find($id);
        if (!$link) {
            $metrics->counterInc('link_ban_total', ['result' => 'not_found']);
            abort(404);
        }
        $link->is_banned = true;
        $link->save();
        Cache::forget("link:$id");
        $metrics->counterInc('link_ban_total', ['result' => 'ok']);
        return response()->noContent();
    }
}
