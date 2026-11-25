<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 100)->unique();
            $table->string('service', 100);
            $table->string('version', 3);
            $table->string('status', 20);
            $table->json('result')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('billing_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->bigInteger('amount_minor');
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('service_prices', function (Blueprint $table) {
            $table->id();
            $table->string('service', 100);
            $table->string('version', 3);
            $table->unsignedBigInteger('price_minor');
            $table->unsignedBigInteger('error_charge_minor')->default(0);
            $table->boolean('available')->default(false);
            $table->unique(['service', 'version']);
        });

        Schema::create('live_access_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('business_name');
            $table->string('registration_number', 100);
            $table->text('use_case');
            $table->string('document_path');
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });

        Schema::create('portal_pages', function (Blueprint $table) {
            $table->string('slug', 100)->primary();
            $table->string('title');
            $table->text('body');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->text('api_key')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('api_key');
        });

        Schema::dropIfExists('portal_pages');
        Schema::dropIfExists('live_access_applications');
        Schema::dropIfExists('service_prices');
        Schema::dropIfExists('billing_entries');
        Schema::dropIfExists('verification_requests');
    }
};
