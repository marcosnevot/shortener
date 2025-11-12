<?php

namespace Database\Factories;

use App\Models\Link;
use Illuminate\Database\Eloquent\Factories\Factory;

class LinkFactory extends Factory
{
    protected $model = Link::class;

    public function definition(): array
    {
        return [
            'slug'         => 'tmp_'.bin2hex(random_bytes(4)),
            'id_b62'       => 'tmp_'.bin2hex(random_bytes(4)),
            'sig'          => substr(hash('sha256', random_bytes(8)), 0, 11),
            'url'          => 'https://example.com',
            'expires_at'   => null,
            'max_clicks'   => null,
            'domain_scope' => null,
            'is_banned'    => false,
            'clicks_count' => 0,
        ];
    }
}
