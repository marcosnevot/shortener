<?php

namespace Tests\Feature;

use App\Models\Link;
use App\Services\SlugService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ApiLinkControllerTest extends TestCase
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

    public function test_create_show_ban_delete()
    {
        $h = $this->seedToken();

        // create
        $res = $this->postJson('/api/v1/links', ['url' => 'https://example.com'], $h)
            ->assertCreated()
            ->json();
        $id = $res['id'];

        // show
        $this->getJson("/api/v1/links/{$id}", $h)
            ->assertOk()
            ->assertJsonPath('id', $id);

        // ban
        $this->postJson("/api/v1/links/{$id}/ban", [], $h)->assertNoContent();

        // delete
        $this->deleteJson("/api/v1/links/{$id}", [], $h)->assertNoContent();

        // show 404
        $this->getJson("/api/v1/links/{$id}", $h)->assertNotFound();
    }
}
