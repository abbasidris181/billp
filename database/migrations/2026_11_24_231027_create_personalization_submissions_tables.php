<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personalization_tracking', function (Blueprint $table) {
            $table->char('tracking_digest', 64)->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('personalization_submissions', function (Blueprint $table) {
            $table->unsignedBigInteger('provider_id')->primary();
            $table->char('tracking_digest', 64);
            $table->foreign('tracking_digest')->references('tracking_digest')->on('personalization_tracking')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personalization_submissions');
        Schema::dropIfExists('personalization_tracking');
    }
};
