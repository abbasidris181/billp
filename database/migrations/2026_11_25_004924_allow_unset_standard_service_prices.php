<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_prices', function (Blueprint $table) {
            $table->unsignedBigInteger('price_minor')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('service_prices')->whereNull('price_minor')->update(['price_minor' => 0]);

        Schema::table('service_prices', function (Blueprint $table) {
            $table->unsignedBigInteger('price_minor')->nullable(false)->change();
        });
    }
};
