<?php

namespace Tests\Unit;

use App\Services\UrlNormalizer;
use Tests\TestCase;

class UrlNormalizerTest extends TestCase
{
    public function test_normalize_and_domain()
    {
        $n = app(UrlNormalizer::class);
        $u = $n->normalize(' HTTPs://Example.com/Path/?B=2&A=1#frag ');

        $this->assertSame('example.com', $n->domain($u));
        $this->assertSame('https://example.com/Path/?A=1&B=2', $u);
    }
}
