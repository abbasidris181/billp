<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $nextCheck = now()->addMinutes(3);

        DB::table('instant_ipe_requests')
            ->whereIn('status', ['pending', 'needs_confirmation'])
            ->where('next_check_at', '>', $nextCheck)
            ->update(['next_check_at' => $nextCheck]);
    }

    public function down(): void {}
};
