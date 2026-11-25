<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->char('api_key_digest', 64)->nullable()->unique();
        });

        DB::table('users')->whereNotNull('api_key')->select('id', 'api_key')->chunkById(100, function ($users): void {
            foreach ($users as $user) {
                $key = Crypt::decrypt($user->api_key, false);
                DB::table('users')->where('id', $user->id)->update(['api_key_digest' => hash('sha256', $key)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['api_key_digest']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('api_key_digest');
        });
    }
};
