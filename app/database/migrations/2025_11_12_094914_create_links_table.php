<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('links', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('slug', 64)->unique();           // base62(id) + sig (11)
            $table->string('id_b62', 32)->index();          // redundante útil para consultas/panel
            $table->char('sig', 11);
            $table->text('url');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('expires_at')->nullable()->index();
            $table->unsignedInteger('max_clicks')->nullable();
            $table->unsignedBigInteger('clicks_count')->default(0);
            $table->json('domain_scope')->nullable();       // whitelist opcional por enlace
            $table->boolean('is_banned')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void {
        Schema::dropIfExists('links');
    }
};
