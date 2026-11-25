<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClearanceApiTest extends TestCase
{
    use RefreshDatabase;

    private const string PROVIDER = 'https://clearance.example.test';

    private const string TRACKING_ID = '0SLT9LJS17C5G5D';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.clearance.url' => self::PROVIDER, 'services.clearance.token' => 'mock-provider-token']);
        Http::preventStrayRequests();
    }

    public function test_clearance_preserves_submission_and_status_payloads_with_the_provider_reply(): void
    {
        $key = $this->issueKey();
        $success = ['success' => 'Clearance request submitted successfully.'];
        $successful = ['status' => 'Successful', 'reply' => 'ABC123DEF456GHI', 'nin' => '12345678901', 'name' => 'EXAMPLE CUSTOMER', 'dob' => '1995-04-12'];
        $pending = ['status' => 'New', 'reply' => null, 'nin' => null, 'name' => null, 'dob' => null];
        $statuses = Http::sequence()->push($pending)->push($successful);

        foreach (['Ready for Abis', 'No Record Found', 'Invalid', 'Blocked'] as $reply) {
            $statuses->push(['status' => 'Failed', 'reply' => $reply, 'nin' => null, 'name' => null, 'dob' => null]);
        }

        $statuses->push(['error' => 'Clearance record not found.'], 404);
        Http::fake([
            self::PROVIDER.'/api/clearance' => Http::response($success),
            self::PROVIDER.'/api/clearance/status' => $statuses,
        ]);

        $payload = ['token' => $key, 'tracking_id' => self::TRACKING_ID];
        $this->postJson('/api/clearance', $payload)->assertOk()->assertExactJson($success);
        $this->postJson('/api/clearance/status', $payload)->assertOk()->assertExactJson($pending);
        $this->postJson('/api/clearance/status', $payload)->assertOk()->assertExactJson($successful);
        foreach (['Ready for Abis', 'No Record Found', 'Invalid', 'Blocked'] as $reply) {
            $this->postJson('/api/clearance/status', $payload)->assertOk()
                ->assertExactJson(['status' => 'Failed', 'reply' => $reply, 'nin' => null, 'name' => null, 'dob' => null]);
        }
        $this->postJson('/api/clearance/status', $payload)->assertNotFound()->assertExactJson(['error' => 'Clearance record not found.']);

        Http::assertSentCount(8);
        foreach (['/api/clearance', '/api/clearance/status'] as $path) {
            Http::assertSent(fn (Request $request): bool => $request->url() === self::PROVIDER.$path
                && $request->data() === ['token' => 'mock-provider-token', 'tracking_id' => self::TRACKING_ID]
                && $request->hasHeader('Accept', 'application/json'));
        }

        $this->assertDatabaseCount('clearance_submissions', 1);
        $this->assertDatabaseHas('clearance_submissions', ['tracking_digest' => hash('sha256', self::TRACKING_ID)]);
        $this->get(route('portal.docs.service', ['v1', 'ipeclearing']))->assertOk()
            ->assertSee(['POST '.rtrim(config('app.url'), '/').'/api/clearance', 'POST '.rtrim(config('app.url'), '/').'/api/clearance/status', 'Ready for Abis']);
    }

    public function test_provider_submission_errors_are_returned_without_registering_ownership(): void
    {
        $errors = [
            'Previous clearance failed. Attempt saved but not charged.',
            'Duplicate Entry detected. Attempt saved as Failed.',
            'Insufficient Balance.',
        ];
        $responses = Http::sequence();
        foreach ($errors as $message) {
            $responses->push(['error' => $message], 400);
        }

        Http::fake([self::PROVIDER.'/api/clearance' => $responses]);
        $payload = ['token' => $this->issueKey(), 'tracking_id' => self::TRACKING_ID];

        foreach ($errors as $message) {
            $this->postJson('/api/clearance', $payload)->assertStatus(400)->assertExactJson(['error' => $message]);
        }

        $this->postJson('/api/clearance/status', $payload)->assertNotFound();
        Http::assertSentCount(3);
        $this->assertDatabaseCount('clearance_submissions', 0);
    }

    public function test_clearance_validates_json_and_limits_status_checks_to_the_submitting_account(): void
    {
        Http::fake([self::PROVIDER.'/api/clearance' => Http::response(['success' => 'Clearance request submitted successfully.'])]);
        $first = $this->issueKey();
        $second = $this->issueKey();

        $this->postJson('/api/clearance', ['tracking_id' => self::TRACKING_ID], ['Authorization' => 'Bearer '.$first])->assertUnauthorized();
        $this->postJson('/api/clearance', ['token' => $first, 'tracking_id' => self::TRACKING_ID.' '])
            ->assertUnprocessable()->assertJsonValidationErrors('tracking_id');
        $this->postJson('/api/clearance', ['token' => $first, 'tracking_id' => self::TRACKING_ID])->assertOk();
        $this->postJson('/api/clearance/status', ['token' => $second, 'tracking_id' => self::TRACKING_ID])->assertNotFound();
        $this->postJson('/api/clearance', ['token' => $second, 'tracking_id' => strtolower(self::TRACKING_ID)])->assertStatus(400);
        Http::assertSentCount(1);
    }

    private function issueKey(): string
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('portal.key'))->assertRedirect(route('portal.api'));

        return $user->fresh()->api_key;
    }
}
