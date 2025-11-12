<?php

namespace Tests\Feature;

use App\Models\Link;
use App\Services\SlugService;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RedirectControllerTest extends TestCase
{
    protected function makeUrl(Link $link): string
    {
        $svc = app(SlugService::class);
        [$slug] = $svc->makeSlug($link->id, $link->url, (string) config('shortener.hmac_key'));
        return "/r/{$slug}";
    }

    public function test_head_does_not_consume_click()
    {
        Queue::fake(); // no ejecuta el job de analítica
        $link = Link::factory()->create(['max_clicks' => 3]);
        $url  = $this->makeUrl($link);

        $this->head($url)->assertStatus(302);
        $link->refresh();
        $this->assertSame(0, $link->clicks_count);
    }

    public function test_max_clicks_is_enforced_atomically()
    {
        Queue::fake();
        $link = Link::factory()->create(['max_clicks' => 2]);
        $url  = $this->makeUrl($link);

        $this->get($url)->assertStatus(302);
        $this->get($url)->assertStatus(302);
        $this->get($url)->assertStatus(404);

        $link->refresh();
        $this->assertSame(2, $link->clicks_count);
    }
}
