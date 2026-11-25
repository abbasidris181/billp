<?php

namespace Tests\Feature;

use App\Http\Clients\TrackingProvider;
use App\Jobs\ProcessInstantIpe;
use App\Models\InstantIpeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class InstantIpeTest extends TestCase
{
    use RefreshDatabase;

    private const string FIRST = '0SLT9LJS17C5G5D';

    private const string SECOND = 'ABCDEFGHIJKLMNO';

    private const string THIRD = 'XYZ987UVW654RST';

    public function test_bulk_submission_charges_each_id_once_and_protects_the_batch_report(): void
    {
        Queue::fake();
        config(['services.clearance.url' => 'https://clearance.example.test', 'services.clearance.token' => 'mock-token']);
        Http::preventStrayRequests();
        Http::fake(['https://clearance.example.test/api/clearance' => Http::response(['success' => 'Submitted.'])]);
        $user = $this->fundedUser();
        $this->actingAs($user)->get(route('instant-ipe.index'))->assertOk()->assertSee('₦7.00')->assertDontSee('₦5.00');

        $payload = ['submission_key' => (string) Str::uuid(), 'mode' => 'bulk', 'tracking_ids' => strtolower(self::FIRST.self::SECOND)];
        $this->post(route('instant-ipe.store'), $payload)->assertRedirect(route('instant-ipe.index'));
        $this->post(route('instant-ipe.store'), $payload)->assertRedirect(route('instant-ipe.index'));

        $submission = DB::table('instant_ipe_submissions')->sole();
        $this->assertMatchesRegularExpression('/^[0-9]{8}$/', (string) $submission->batch_id);
        $this->assertSame(1400, (int) $submission->total_minor);
        $this->assertDatabaseCount('instant_ipe_requests', 2);
        $this->assertDatabaseCount('billing_entries', 3);
        $this->assertSame(600, (int) DB::table('billing_entries')->sum('amount_minor'));
        Queue::assertNothingPushed();
        Http::assertSentCount(2);
        $this->get(route('instant-ipe.batch', $submission->batch_id))->assertOk()->assertSee([self::FIRST, self::SECOND, 'Pending 2']);
        $this->get(route('instant-ipe.history'))->assertOk()->assertSee([self::FIRST, self::SECOND, 'Check batch status']);
        $batchCheck = route('instant-ipe.batch.check', $submission->batch_id);
        $this->post($batchCheck)->assertRedirect()->assertSessionHas('notice', 'Batch status checks queued for 2 tracking IDs.');
        Queue::assertPushed(ProcessInstantIpe::class, 2);
        foreach (InstantIpeRequest::pluck('id') as $id) {
            Queue::assertPushed(ProcessInstantIpe::class, fn (ProcessInstantIpe $job) => $job->requestId === $id && $job->check && $job->manual);
        }
        $this->post($batchCheck)->assertRedirect()->assertSessionHas('notice', 'No tracking IDs are eligible for a status check yet.');
        Queue::assertPushed(ProcessInstantIpe::class, 2);

        $this->post(route('instant-ipe.store'), [
            ...$payload, 'submission_key' => (string) Str::uuid(), 'tracking_ids' => self::FIRST.'; '.self::FIRST,
        ])->assertSessionHasErrors('tracking_ids');
        $this->post(route('instant-ipe.store'), [
            ...$payload, 'submission_key' => (string) Str::uuid(), 'mode' => 'single', 'tracking_ids' => self::FIRST,
        ])->assertSessionHasErrors('tracking_ids');
        $this->actingAs(User::factory()->create())->get(route('instant-ipe.batch', $submission->batch_id))->assertNotFound();
        $this->get(route('instant-ipe.history'))->assertDontSee(self::FIRST);
        $this->post($batchCheck)->assertNotFound();
    }

    public function test_queue_checks_refund_only_confirmed_failures_and_preserve_provider_result(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        config(['services.clearance.url' => 'https://clearance.example.test', 'services.clearance.token' => 'mock-token']);
        Http::fake([
            'https://clearance.example.test/api/clearance' => Http::sequence()->push(['success' => 'Submitted.'])->pushFailedConnection()->push(['success' => 'Submitted.']),
            'https://clearance.example.test/api/clearance/status' => Http::sequence()
                ->push(['status' => 'Failed', 'reply' => 'Blocked'])
                ->push(['status' => 'Successful', 'reply' => 'Cleared', 'nin' => '12345678901']),
        ]);

        $this->actingAs($this->fundedUser(3000))->post(route('instant-ipe.store'), [
            'submission_key' => (string) Str::uuid(), 'mode' => 'bulk', 'tracking_ids' => self::FIRST.', '.self::SECOND.'; '.self::THIRD,
        ])->assertRedirect();

        [$first, $second, $third] = InstantIpeRequest::orderBy('id')->get();
        Http::assertSentCount(2);
        Queue::assertPushed(ProcessInstantIpe::class, 1);
        Queue::assertPushed(ProcessInstantIpe::class, fn (ProcessInstantIpe $job) => $job->requestId === $third->id && ! $job->check);
        $this->assertSame('queued', $third->status);
        $provider = app(TrackingProvider::class);
        (new ProcessInstantIpe($third->id))->handle($provider);
        $this->assertSame('pending', $third->fresh()->status);
        $this->assertSame('pending', $first->fresh()->status);
        $this->assertSame('needs_confirmation', $second->fresh()->status);
        $this->assertDatabaseCount('billing_entries', 4);
        $this->assertTrue($first->fresh()->next_check_at->between(now()->addMinutes(2), now()->addMinutes(3)));

        $this->post(route('instant-ipe.check', $first->id))->assertRedirect()->assertSessionHas('notice', 'Status check queued.');
        $this->post(route('instant-ipe.check', $first->id))->assertRedirect()->assertSessionHas('notice', 'Status check is unavailable. Wait 15 seconds between checks on the same request.');
        (new ProcessInstantIpe($first->id, check: true, manual: true))->handle($provider);
        (new ProcessInstantIpe($first->id, check: true, manual: true))->handle($provider);
        $this->assertSame('failed', $first->fresh()->status);
        $this->assertNotNull($first->fresh()->refunded_at);

        $this->travel(3)->minutes();
        (new ProcessInstantIpe($second->id, check: true))->handle($provider);
        $this->assertSame('successful', $second->fresh()->status);
        $this->assertSame('12345678901', $second->fresh()->nin);
        $this->assertDatabaseCount('billing_entries', 5);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://clearance.example.test/api/clearance'
            && $request->data() === ['token' => 'mock-token', 'tracking_id' => self::FIRST]);
        Http::assertSentCount(5);
        $this->get(route('instant-ipe.batch', DB::table('instant_ipe_submissions')->value('batch_id')))->assertOk()
            ->assertSee([self::FIRST.'=Blocked', self::SECOND.'=Cleared=12345678901', 'Successful 1', 'Failed 1']);
    }

    public function test_insufficient_funds_and_invalid_single_ids_do_not_queue_or_charge(): void
    {
        Queue::fake();
        $this->actingAs($this->fundedUser(100));
        $payload = ['submission_key' => (string) Str::uuid(), 'mode' => 'single', 'tracking_ids' => self::FIRST];
        $this->post(route('instant-ipe.store'), $payload)->assertSessionHasErrors('tracking_ids');
        $this->post(route('instant-ipe.store'), [...$payload, 'tracking_ids' => self::FIRST.self::SECOND])->assertSessionHasErrors('tracking_ids');
        $this->assertDatabaseCount('billing_entries', 1);
        $this->assertDatabaseCount('instant_ipe_requests', 0);
        Queue::assertNothingPushed();
    }

    private function fundedUser(int $balance = 2000): User
    {
        $user = User::factory()->create();
        DB::table('service_prices')->insert([
            ['service' => 'instant-ipe-single', 'version' => 'v1', 'price_minor' => 700, 'available' => true],
            ['service' => 'instant-ipe-bulk', 'version' => 'v1', 'price_minor' => 500, 'available' => true],
        ]);
        DB::table('billing_entries')->insert(['user_id' => $user->id, 'description' => 'Credit', 'amount_minor' => $balance]);

        return $user;
    }
}
