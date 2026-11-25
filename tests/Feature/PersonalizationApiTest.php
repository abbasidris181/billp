<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PersonalizationApiTest extends TestCase
{
    use RefreshDatabase;

    private const string PROVIDER = 'https://tracking.example.test';

    private const string TRACKING_ID = 'S7Y0OG319000HEM';

    private const int PROVIDER_ID = 12345;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.clearance.url' => self::PROVIDER, 'services.clearance.token' => 'mock-provider-token']);
        Http::preventStrayRequests();
    }

    public function test_submission_and_both_check_methods_preserve_provider_payloads(): void
    {
        $base = $this->acknowledgement();
        $pending = [...$base, 'data' => ['idNumber' => self::TRACKING_ID, 'photo' => null]];
        $successful = [...$base, 'status' => 'Successful', 'data' => ['idNumber' => '12345678901', 'photo' => 'data:image/jpeg;base64,EXAMPLE']];
        $failed = [...$base, 'status' => 'Failed', 'data' => ['idNumber' => 'XYZ987UVW654RST', 'photo' => null]];

        Http::fake([
            self::PROVIDER.'/api/personalization' => Http::response($base, 201),
            self::PROVIDER.'/api/personalization/check' => Http::sequence()->push($pending)->push($successful)->push($failed),
        ]);

        $key = $this->issueKey();
        $this->postJson('/api/personalization', ['token' => $key, 'tracking_id' => self::TRACKING_ID, 'type' => 'info'])
            ->assertCreated()->assertExactJson($base);
        $this->postJson('/api/personalization/check', ['token' => $key, 'id' => self::PROVIDER_ID])->assertOk()->assertExactJson($pending);
        $this->postJson('/api/personalization/check', ['token' => $key, 'tracking_id' => self::TRACKING_ID])->assertOk()->assertExactJson($successful);
        $this->postJson('/api/personalization/check', ['token' => $key, 'id' => self::PROVIDER_ID])->assertOk()->assertExactJson($failed);
        $this->assertDatabaseCount('personalization_submissions', 1);

        foreach ([
            ['/api/personalization', ['tracking_id' => self::TRACKING_ID, 'type' => 'info']],
            ['/api/personalization/check', ['id' => self::PROVIDER_ID]],
            ['/api/personalization/check', ['tracking_id' => self::TRACKING_ID]],
        ] as [$path, $data]) {
            Http::assertSent(fn (Request $request): bool => $request->url() === self::PROVIDER.$path
                && $request->data() === ['token' => 'mock-provider-token', ...$data]);
        }

        $this->get(route('portal.docs.service', ['v1', 'personalization']))->assertOk()->assertSee([
            'POST '.rtrim(config('app.url'), '/').'/api/personalization', 'POST '.rtrim(config('app.url'), '/').'/api/personalization/check', 'In-Progress', 'Failed',
        ]);
    }

    public function test_provider_errors_validation_and_scope_boundaries(): void
    {
        $insufficient = ['success' => false, 'message' => 'Insufficient balance.'];
        $notFound = ['success' => false, 'message' => 'Personalization not found.'];
        Http::fake([
            self::PROVIDER.'/api/personalization' => Http::sequence()->push($insufficient, 400)->push($this->acknowledgement()),
            self::PROVIDER.'/api/personalization/check' => Http::sequence()->push($notFound, 404)
                ->push([...$this->acknowledgement(), 'id' => 999]),
        ]);

        $owner = $this->issueKey();
        $anotherUser = $this->issueKey();
        $body = ['tracking_id' => self::TRACKING_ID, 'type' => 'info'];
        $this->postJson('/api/personalization', $body)->assertUnauthorized();
        $this->postJson('/api/personalization', ['token' => $owner, ...$body, 'tracking_id' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('tracking_id');
        $this->postJson('/api/personalization/check', ['token' => $owner, 'id' => 12345, 'tracking_id' => self::TRACKING_ID])
            ->assertUnprocessable()->assertJsonValidationErrors(['tracking_id', 'id']);
        $this->postJson('/api/personalization/check', ['token' => $owner])
            ->assertUnprocessable()->assertJsonValidationErrors(['tracking_id', 'id']);
        $this->postJson('/api/personalization', ['token' => $owner, ...$body])->assertStatus(400)->assertExactJson($insufficient);
        $this->assertDatabaseCount('personalization_submissions', 0);
        $this->postJson('/api/personalization', ['token' => $owner, ...$body])->assertOk();

        $this->postJson('/api/personalization/check', ['token' => $anotherUser, 'id' => self::PROVIDER_ID])->assertNotFound()->assertExactJson($notFound);
        $this->postJson('/api/personalization/check', ['token' => $anotherUser, 'tracking_id' => self::TRACKING_ID])->assertNotFound()->assertExactJson($notFound);
        $this->postJson('/api/personalization/check', ['token' => $owner, 'id' => self::PROVIDER_ID])->assertNotFound()->assertExactJson($notFound);
        $this->postJson('/api/personalization/check', ['token' => $owner, 'id' => self::PROVIDER_ID])->assertStatus(502);
        Http::assertSentCount(4);
    }

    /** @return array{success: bool, id: int, tracking_id: string, status: string, express: bool, message: string} */
    private function acknowledgement(): array
    {
        return [
            'success' => true, 'id' => self::PROVIDER_ID, 'tracking_id' => self::TRACKING_ID,
            'status' => 'In-Progress', 'express' => false,
            'message' => 'Personalization saved. Use the id or tracking_id to check the result.',
        ];
    }

    private function issueKey(): string
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('portal.key'))->assertRedirect(route('portal.api'));

        return $user->fresh()->api_key;
    }
}
