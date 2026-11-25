<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('business_name')->nullable();
            $table->string('role', 20)->default('user');
            $table->string('account_status', 20)->default('active');
            $table->unsignedBigInteger('session_version')->default(0);
        });

        DB::table('users')->where('is_admin', true)->update(['role' => 'admin']);
        if (filled(config('admin.email'))) {
            DB::table('users')->where('is_admin', true)->where('email', strtolower(config('admin.email')))
                ->update(['role' => 'super_admin']);
        }

        Schema::table('billing_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('actor_id')->nullable();
        });

        Schema::create('user_service_prices', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_price_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('price_minor');
            $table->primary(['user_id', 'service_price_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_service_prices');
        Schema::table('billing_entries', fn (Blueprint $table) => $table->dropColumn('actor_id'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['business_name', 'role', 'account_status', 'session_version']));
    }
};
