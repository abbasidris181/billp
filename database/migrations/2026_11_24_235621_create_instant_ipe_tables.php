<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instant_ipe_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('batch_id')->nullable()->unique();
            $table->unsignedInteger('request_count');
            $table->unsignedBigInteger('total_minor');
        });

        Schema::create('instant_ipe_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('submission_id')->constrained('instant_ipe_submissions')->cascadeOnDelete();
            $table->uuid('reference')->unique();
            $table->char('tracking_id', 15);
            $table->unsignedBigInteger('price_minor');
            $table->string('status', 25)->default('queued');
            $table->text('reply')->nullable();
            $table->text('nin')->nullable();
            $table->timestamp('next_check_at')->nullable();
            $table->timestamp('last_manual_check_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'id']);
            $table->index(['user_id', 'tracking_id', 'status']);
            $table->index(['status', 'next_check_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instant_ipe_requests');
        Schema::dropIfExists('instant_ipe_submissions');
    }
};
