<?php

namespace Tests\Unit;

use App\Services\SlugService;
use Tests\TestCase;

class SlugServiceTest extends TestCase
{
    public function test_make_and_parse_slug_hmac()
    {
        $svc = app(SlugService::class);
        [$slug] = $svc->makeSlug(123, 'https://example.com/?a=1', 'key');

        // no asumimos separador; validamos por parseo y firma
        [$b62, $sig] = $svc->parseSlug($slug);

        $this->assertSame(123, $svc->base62Decode($b62));
        $this->assertSame(11, strlen($sig));
        $this->assertSame($svc->sign(123, 'https://example.com/?a=1', 'key'), $sig);
    }
}
