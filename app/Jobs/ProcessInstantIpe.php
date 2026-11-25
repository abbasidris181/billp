<?php

namespace App\Jobs;

use App\Http\Clients\TrackingProvider;
use App\Models\InstantIpeRequest;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;

class ProcessInstantIpe implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600;

    public int $tries = 1;

    public int $timeout = 45;

    public function __construct(public int $requestId, public bool $check = false, public bool $manual = false) {}

    public function uniqueId(): string
    {
        return $this->requestId.($this->check ? ':check' : ':submit');
    }

    public function handle(TrackingProvider $provider): bool
    {
        $query = InstantIpeRequest::whereKey($this->requestId);

        if ($this->check) {
            $query->whereIn('status', InstantIpeRequest::CHECKABLE);
            if (! $this->manual) {
                $query->where('next_check_at', '<=', now());
            }
            $claimed = $query->update(['next_check_at' => now()->addMinutes(3)]);
        } else {
            $claimed = $query->where('status', 'queued')->update(['status' => 'submitting']);
        }

        if (! $claimed) {
            return true;
        }

        $record = InstantIpeRequest::findOrFail($this->requestId);
        $path = $this->check ? '/api/clearance/status' : '/api/clearance';

        try {
            $response = $provider->post($path, ['tracking_id' => $record->tracking_id]);
        } catch (ConnectionException) {
            $this->settle($record, 'needs_confirmation', 'Connection failed or timed out.');

            return false;
        }

        if ($response === null) {
            $this->settle($record, $this->check ? 'needs_confirmation' : 'failed', 'The clearance provider is unavailable.');

            return true;
        }

        $reply = $response->json('reply');
        $reply = is_string($reply) ? $reply : null;
        $error = $response->json('error');
        $error = is_string($error) ? $error : null;

        if ($this->check && $response->successful()) {
            $status = match ($response->json('status')) {
                'Successful' => 'successful',
                'Failed' => 'failed',
                'New', 'Pending', 'In-Progress' => 'pending',
                default => 'needs_confirmation',
            };
            $nin = $response->json('nin');
            $this->settle($record, $status, $reply ?? $error, $status === 'successful' && is_string($nin) ? $nin : null);

            return true;
        }

        if (! $this->check && $response->successful() && is_string($response->json('success')) && filled($response->json('success'))) {
            $this->settle($record, 'pending', $response->json('success'));

            return true;
        }

        $confirmedFailure = ! $this->check && $response->status() === 400 && in_array($error, [
            'Previous clearance failed. Attempt saved but not charged.',
            'Duplicate Entry detected. Attempt saved as Failed.',
            'Insufficient Balance.',
        ], true);

        $this->settle($record, $confirmedFailure ? 'failed' : 'needs_confirmation', $reply ?? $error ?? 'The provider outcome could not be confirmed.');

        return ! in_array($response->status(), [408, 504], true);
    }

    private function settle(InstantIpeRequest $record, string $status, ?string $reply, ?string $nin = null): void
    {
        DB::transaction(function () use ($record, $status, $reply, $nin): void {
            DB::table('users')->where('id', $record->user_id)->lockForUpdate()->first();
            $current = InstantIpeRequest::whereKey($record->id)->lockForUpdate()->first();

            if (! $current || ! in_array($current->status, InstantIpeRequest::UNRESOLVED, true)) {
                return;
            }

            $current->status = $status;
            $current->reply = $reply;
            $current->nin = $nin;
            $current->next_check_at = in_array($status, InstantIpeRequest::CHECKABLE, true) ? now()->addMinutes(3) : null;
            if ($status === 'failed' && $current->refunded_at === null) {
                DB::table('billing_entries')->insert([
                    'user_id' => $current->user_id,
                    'description' => 'Instant-ipe refund · '.$current->reference,
                    'amount_minor' => $current->price_minor,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $current->refunded_at = now();
            }
            $current->save();
        }, 3);
    }
}
