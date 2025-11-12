<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->char('token_hash', 64)->unique();   // SHA-256 del token plano
            $table->json('scopes');                      // p.ej. ["links:read","links:write"]
            $table->dateTime('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('api_tokens');
    }
};
