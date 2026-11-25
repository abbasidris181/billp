<?php

use App\Jobs\ProcessInstantIpe;
use App\Models\InstantIpeRequest;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    InstantIpeRequest::where('status', 'submitting')->where('updated_at', '<=', now()->subSeconds(90))
        ->update(['status' => 'needs_confirmation', 'next_check_at' => now()]);
    InstantIpeRequest::where('status', 'queued')->where('created_at', '<=', now()->subMinutes(2))
        ->whereExists(fn ($query) => $query->from('instant_ipe_submissions')
            ->whereColumn('instant_ipe_submissions.id', 'instant_ipe_requests.submission_id')
            ->where(fn ($lease) => $lease->whereNull('direct_until')->orWhere('direct_until', '<=', now())))
        ->select('id')
        ->eachById(fn (InstantIpeRequest $record) => ProcessInstantIpe::dispatch($record->id)->onConnection('database')->onQueue('instant-ipe'));
    InstantIpeRequest::whereIn('status', InstantIpeRequest::CHECKABLE)->where('next_check_at', '<=', now())->select('id')
        ->eachById(fn (InstantIpeRequest $record) => ProcessInstantIpe::dispatch($record->id, check: true)->onConnection('database')->onQueue('instant-ipe'));
})->name('instant-ipe-recovery-and-status')->everyMinute()->withoutOverlapping(5);
