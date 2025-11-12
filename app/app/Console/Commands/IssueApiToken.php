<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ApiToken;

class IssueApiToken extends Command
{
    protected $signature = 'token:issue {name=admin}';
    protected $description = 'Issue an API token and print the plaintext once';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $plain = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $plain);

        $token = ApiToken::create([
            'name' => $name,
            'token_hash' => $hash,
            'scopes' => ['links:read','links:write'],
            'last_used_at' => null,
        ]);

        $this->info("Token created for {$name} (store it now):");
        $this->line($plain);

        return self::SUCCESS;
    }
}
