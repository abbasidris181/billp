<?php

namespace App\Http\Controllers;

use App\Http\Clients\TrackingProvider;
use App\Jobs\ProcessInstantIpe;
use App\Models\InstantIpeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InstantIpeController extends Controller
{
    private const MODES = ['single', 'bulk'];

    public function index(Request $request): View
    {
        return view('portal.instant-ipe.index', [
            'balance' => DB::table('billing_entries')->where('user_id', $request->user()->id)->sum('amount_minor'),
            'unitPrice' => $this->unitPrice($request->user()->id),
            'records' => $this->records($request->user()->id),
        ]);
    }

    public function history(Request $request): View
    {
        return view('portal.instant-ipe.history', ['records' => $this->records($request->user()->id)]);
    }

    private function records(int $userId): LengthAwarePaginator
    {
        return InstantIpeRequest::query()->join('instant_ipe_submissions as submission', 'submission.id', '=', 'instant_ipe_requests.submission_id')
            ->where('instant_ipe_requests.user_id', $userId)
            ->orderByDesc('instant_ipe_requests.id')->paginate(20, ['instant_ipe_requests.*', 'submission.batch_id'])
            ->withPath(route('instant-ipe.index'));
    }

    public function store(Request $request, TrackingProvider $provider): RedirectResponse
    {
        $data = $request->validate([
            'submission_key' => ['required', 'uuid'],
            'mode' => ['required', Rule::in(self::MODES)],
            'tracking_ids' => ['required', 'string'],
        ]);
        $trackingIds = $this->trackingIds($data['tracking_ids'], $data['mode']);
        $userId = $request->user()->id;

        [$submission, $created] = DB::transaction(function () use ($data, $trackingIds, $userId): array {
            $account = DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            abort_unless($account?->account_status === 'active', 403);
            $old = DB::table('instant_ipe_submissions')->find($data['submission_key']);

            if ($old) {
                abort_unless((int) $old->user_id === $userId, 409);

                return [$old, false];
            }

            $price = $this->unitPrice($userId);
            if ($price === null) {
                throw ValidationException::withMessages(['mode' => 'Instant-ipe is currently unavailable.']);
            }

            foreach (array_chunk($trackingIds, 500) as $chunk) {
                $unresolved = InstantIpeRequest::where('user_id', $userId)->whereIn('tracking_id', $chunk)
                    ->whereIn('status', InstantIpeRequest::UNRESOLVED)->pluck('tracking_id');
                if ($unresolved->isNotEmpty()) {
                    throw ValidationException::withMessages(['tracking_ids' => 'An unresolved request already exists for '.$unresolved->first().'.']);
                }
            }

            $total = $price * count($trackingIds);
            if (DB::table('billing_entries')->where('user_id', $userId)->sum('amount_minor') < $total) {
                throw ValidationException::withMessages(['tracking_ids' => 'Insufficient wallet balance.']);
            }

            $submission = $this->reserveSubmission($data['submission_key'], $userId, count($trackingIds), $total);
            $now = now();

            foreach (array_chunk($trackingIds, 500) as $chunk) {
                $rows = [];
                $entries = [];
                foreach ($chunk as $trackingId) {
                    $reference = (string) Str::uuid();
                    $rows[] = [
                        'user_id' => $userId, 'submission_id' => $submission->id, 'reference' => $reference,
                        'tracking_id' => $trackingId, 'price_minor' => $price, 'created_at' => $now, 'updated_at' => $now,
                    ];
                    $entries[] = [
                        'user_id' => $userId, 'description' => 'Instant-ipe charge · '.$reference,
                        'amount_minor' => -$price, 'created_at' => $now, 'updated_at' => $now,
                    ];
                }
                DB::table('instant_ipe_requests')->insert($rows);
                DB::table('billing_entries')->insert($entries);
            }

            return [$submission, true];
        }, 3);

        if ($created) {
            $remaining = InstantIpeRequest::where('submission_id', $submission->id)->where('status', 'queued')->select('id');
            $lease = DB::table('instant_ipe_submissions')->where('id', $submission->id);

            try {
                $sentDirectly = (clone $remaining)->eachById(function (InstantIpeRequest $record) use ($provider, $lease): bool {
                    $lease->update(['direct_until' => now()->addMinutes(2)]);

                    return (new ProcessInstantIpe($record->id))->handle($provider);
                });

                if (! $sentDirectly) {
                    $remaining->eachById(fn (InstantIpeRequest $record) => ProcessInstantIpe::dispatch($record->id)
                        ->onConnection('database')->onQueue('instant-ipe'));
                }
            } finally {
                $lease->update(['direct_until' => null]);
            }
        }

        return to_route('instant-ipe.index')->with('instant_ipe_receipt', [
            'count' => $submission->request_count,
            'total' => $submission->total_minor,
            'batch_id' => $submission->batch_id,
        ]);
    }

    public function check(Request $request, int $id): RedirectResponse
    {
        $record = InstantIpeRequest::where('user_id', $request->user()->id)->findOrFail($id);
        if (! $this->queueManualCheck($record->id)) {
            return to_route('instant-ipe.index')->with('notice', 'Status check is unavailable. Wait 15 seconds between checks on the same request.');
        }

        return to_route('instant-ipe.index')->with('notice', 'Status check queued.');
    }

    public function checkBatch(Request $request, int $batch): RedirectResponse
    {
        $submission = $this->ownedBatch($request->user()->id, $batch);
        $queued = 0;

        InstantIpeRequest::where('submission_id', $submission->id)->whereIn('status', InstantIpeRequest::CHECKABLE)->select('id')
            ->eachById(function (InstantIpeRequest $record) use (&$queued): void {
                if ($this->queueManualCheck($record->id)) {
                    $queued++;
                }
            });

        return back()->with('notice', $queued > 0
            ? 'Batch status checks queued for '.$queued.' '.Str::plural('tracking ID', $queued).'.'
            : 'No tracking IDs are eligible for a status check yet.');
    }

    private function queueManualCheck(int $id): bool
    {
        $updated = InstantIpeRequest::whereKey($id)->whereIn('status', InstantIpeRequest::CHECKABLE)
            ->where(fn ($query) => $query->whereNull('last_manual_check_at')->orWhere('last_manual_check_at', '<=', now()->subSeconds(15)))
            ->update(['last_manual_check_at' => now()]);

        if ($updated) {
            ProcessInstantIpe::dispatch($id, check: true, manual: true)->onConnection('database')->onQueue('instant-ipe');
        }

        return (bool) $updated;
    }

    private function ownedBatch(int $userId, int $batch): object
    {
        $submission = DB::table('instant_ipe_submissions')->where('user_id', $userId)->where('batch_id', $batch)->first();
        abort_if($submission === null, 404);

        return $submission;
    }

    public function batch(Request $request, int $batch): View
    {
        $submission = $this->ownedBatch($request->user()->id, $batch);

        $records = InstantIpeRequest::where('submission_id', $submission->id)->orderBy('id')->get();
        $report = $records->map(function (InstantIpeRequest $record): string {
            return implode('=', array_filter([$record->tracking_id, $record->reply, $record->nin], fn ($value) => filled($value)));
        })->implode("\n");

        return view('portal.instant-ipe.batch', [
            'batch' => $batch,
            'report' => $report,
            'counts' => $records->countBy('status'),
            'canCheck' => $records->contains(fn (InstantIpeRequest $record): bool => in_array($record->status, InstantIpeRequest::CHECKABLE, true)),
        ]);
    }

    private function unitPrice(int $userId): ?int
    {
        $price = DB::table('service_prices as price')
            ->leftJoin('user_service_prices as override', fn ($join) => $join->on('override.service_price_id', '=', 'price.id')->where('override.user_id', $userId))
            ->where('price.version', 'v1')->where('price.available', true)->where('price.service', 'instant-ipe-single')
            ->selectRaw('COALESCE(override.price_minor, price.price_minor) as amount')->first();

        return $price?->amount === null ? null : (int) $price->amount;
    }

    /** @return list<string> */
    private function trackingIds(string $input, string $mode): array
    {
        $input = strtoupper(trim($input));
        $segments = $mode === 'single' ? [$input] : preg_split('/[\s,;]+/', $input, -1, PREG_SPLIT_NO_EMPTY);
        $trackingIds = [];

        foreach ($segments as $segment) {
            if (! ctype_alnum($segment) || strlen($segment) % 15 !== 0 || ($mode === 'single' && strlen($segment) !== 15)) {
                throw ValidationException::withMessages(['tracking_ids' => 'Each tracking ID must contain exactly 15 letters or digits.']);
            }

            foreach (str_split($segment, 15) as $id) {
                if (isset($trackingIds[$id])) {
                    throw ValidationException::withMessages(['tracking_ids' => 'Duplicate tracking ID: '.$id.'.']);
                }
                $trackingIds[$id] = $id;
            }
        }

        if ($trackingIds === []) {
            throw ValidationException::withMessages(['tracking_ids' => 'Enter at least one tracking ID.']);
        }

        return array_values($trackingIds);
    }

    private function reserveSubmission(string $id, int $userId, int $count, int $total): object
    {
        $table = DB::table('instant_ipe_submissions');

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $row = [
                'id' => $id, 'user_id' => $userId, 'batch_id' => $count > 1 ? random_int(10000000, 99999999) : null,
                'request_count' => $count, 'total_minor' => $total, 'direct_until' => now()->addMinutes(2),
            ];
            if ($table->insertOrIgnore($row)) {
                return (object) $row;
            }
        }

        abort(409, 'Unable to reserve the submission. Please retry.');
    }
}
