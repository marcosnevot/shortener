<?php

namespace Tests\Feature;

use App\Models\Link;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StatsControllerTest extends TestCase
{
    private function seedToken(?string $plain = null): array
    {
        $plain = $plain ?? bin2hex(random_bytes(16));

        $col = \Illuminate\Support\Facades\Schema::hasColumn('api_tokens','token_hash') ? 'token_hash' : 'token';
        $val = $col === 'token_hash' ? hash('sha256', $plain) : $plain;

        $extra = [];
        if (\Illuminate\Support\Facades\Schema::hasColumn('api_tokens','scopes')) {
            $extra['scopes'] = json_encode([
                'links:create','links:read','links:stats','links:ban','links:delete'
            ]);
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('api_tokens','revoked')) {
            $extra['revoked'] = 0;
        }

        \Illuminate\Support\Facades\DB::table('api_tokens')
            ->insertOrIgnore(array_merge(['name' => 't', $col => $val], $extra));

        return ['Authorization' => "Bearer {$plain}"];
    }

    public function test_stats_smoke()
    {
        $h = $this->seedToken();

        $l = Link::factory()->create();

        DB::table('clicks_agg')->insert([
            'link_id' => $l->id,
            'ts_hour' => now()->startOfHour()->toDateTimeString(),
            'referrer_domain' => 'localhost',
            'country_code' => '',
            'device_class' => 'desktop',
            'count' => 2,
        ]);

        $this->getJson("/api/v1/links/{$l->id}/stats?range=7d", $h)
            ->assertOk()
            ->assertJsonStructure(['range','series','by_referrer','by_country','by_device','k_anon']);
    }
}
