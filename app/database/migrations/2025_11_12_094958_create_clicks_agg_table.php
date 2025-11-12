<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('clicks_agg', function (Blueprint $table) {
            $table->unsignedBigInteger('link_id');
            $table->dateTime('ts_hour');                               // truncado a la hora
            $table->string('referrer_domain', 255);
            $table->char('country_code', 2)->default('');              // no null para PK compuesta
            $table->enum('device_class', ['mobile','desktop','tablet','bot','other']);
            $table->unsignedBigInteger('count')->default(0);

            $table->primary(['link_id','ts_hour','referrer_domain','country_code','device_class']);
            $table->index('ts_hour');

            $table->foreign('link_id')->references('id')->on('links')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('clicks_agg');
    }
};
