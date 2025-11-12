<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Link;
use App\Services\SlugService;
use App\Services\UrlNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class PanelController extends Controller
{
    public function index()
    {
        $links = Link::orderByDesc('id')->paginate(20);
        return view('panel.index', compact('links'));
    }

    public function show(int $id)
    {
        $link = Link::findOrFail($id);
        return view('panel.show', compact('link'));
    }

    public function store(Request $request, UrlNormalizer $norm, SlugService $slugger)
    {
        $data = $request->validate([
            'url'            => ['required','string','max:2048'],
            'expires_at'     => ['nullable','date','after:now'],
            'max_clicks'     => ['nullable','integer','min:1'],
        ]);

        try {
            $url = $norm->normalize($data['url']);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['url' => $e->getMessage()]);
        }

        $placeholder = 'tmp_'.bin2hex(random_bytes(8));
        $link = new Link();
        $link->slug         = $placeholder;
        $link->id_b62       = $placeholder;
        $link->sig          = substr(hash('sha256', $placeholder), 0, 11);
        $link->url          = $url;
        $link->expires_at   = $data['expires_at'] ?? null;
        $link->max_clicks   = $data['max_clicks'] ?? null;
        $link->is_banned    = false;
        $link->save();

        [$slug, $idB62, $sig] = $slugger->makeSlug(
            $link->id, $link->url, (string) config('shortener.hmac_key')
        );
        $link->slug = $slug; $link->id_b62 = $idB62; $link->sig = $sig; $link->save();

        Cache::put("link:{$link->id}", $link, now()->addMinutes(10));

        return redirect()->route('panel.index')->with('ok', 'Link creado');
    }

    public function ban(int $id)
    {
        $link = Link::findOrFail($id);
        $link->is_banned = true;
        $link->save();
        Cache::forget("link:{$id}");
        return back()->with('ok', 'Link baneado');
    }

    public function destroy(int $id)
    {
        $link = Link::findOrFail($id);
        $link->delete();
        Cache::forget("link:{$id}");
        return back()->with('ok', 'Link eliminado');
    }
}
