<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('abuse_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('link_id');
            $table->string('reason', 255);
            $table->text('details')->nullable();
            $table->dateTime('created_at');  // suficiente para este caso

            $table->foreign('link_id')->references('id')->on('links')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('abuse_reports');
    }
};
