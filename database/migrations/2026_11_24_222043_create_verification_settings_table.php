<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_settings', function (Blueprint $table) {
            $table->string('version', 3)->primary();
            $table->boolean('charge_errors')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_settings');
    }
};
