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
        if (! Schema::hasColumn('users', 'api_key_selector')) {
            Schema::table('users', function (Blueprint $table) {
                $table->char('api_key_selector', 26)->nullable()->index();
            });
        }

        $this->copyKeys('api_key_selector', fn (string $key): string => substr($key, 0, 26));

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['api_key_digest']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('api_key_digest');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->char('api_key_digest', 64)->nullable()->unique();
        });

        $this->copyKeys('api_key_digest', fn (string $key): string => hash('sha256', $key));

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['api_key_selector']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('api_key_selector');
        });
    }

    private function copyKeys(string $column, Closure $value): void
    {
        DB::table('users')->whereNotNull('api_key')->select('id', 'api_key')->chunkById(100, function ($users) use ($column, $value): void {
            foreach ($users as $user) {
                $key = Crypt::decrypt($user->api_key, false);
                DB::table('users')->where('id', $user->id)->update([$column => $value($key)]);
            }
        });
    }
};
