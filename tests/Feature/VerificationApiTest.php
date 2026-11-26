<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VerificationApiTest extends TestCase
{
    use RefreshDatabase;

    private const string PROVIDER = 'https://verification.example.test';

    private function issueKey(User $user): string
    {
        $this->actingAs($user)->post(route('portal.key'))->assertRedirect(route('portal.api'));

        return $user->fresh()->api_key;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.verification.url' => self::PROVIDER, 'services.verification.token' => 'mock-provider-token']);
        Http::preventStrayRequests();
    }

    public function test_each_identity_endpoint_forwards_the_expected_contract_and_records_only_metadata(): void
    {
        $user = User::factory()->create();
        $key = $this->issueKey($user);
        Http::fake([
            self::PROVIDER.'/*' => Http::response([
                'status' => true,
                'cached' => false,
                'message' => [
                    'firstName' => 'EXAMPLE',
                    'address' => ['state' => 'Lagos', 'internal' => 'private'],
                    'photo' => 'BASE64_PHOTO',
                    'religion' => ' ',
                    'providerMeta' => 'internal',
                ],
                'transID' => 'upstream-reference',
            ]),
        ]);

        foreach (['nin' => ['v3', '12345678901'], 'phone' => ['v1', '08012345678'], 'bvn' => ['v1', '12345678901']] as $type => [$version, $number]) {
            $payload = ['type' => $type, 'idNumber' => $number, 'consent' => true];
            $response = $this->postJson(route('api.v1.'.$type), $payload, ['Authorization' => 'Bearer '.$key])
                ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
                ->assertJsonPath('status', true)->assertJsonPath('cached', false)
                ->assertJsonPath('message.address.state', 'Lagos')->assertJsonMissingPath('message.address.internal')
                ->assertJsonPath('message.photo', 'BASE64_PHOTO')->assertJsonMissingPath('message.providerMeta');
            if ($type === 'nin') {
                $response->assertJsonPath('message.religion', ' ');
            } else {
                $response->assertJsonMissingPath('message.religion');
            }

            $this->assertDatabaseHas('verification_requests', [
                'user_id' => $user->id, 'reference' => $response->json('transID'),
                'service' => $type, 'version' => 'v1', 'status' => 'successful', 'result' => null,
            ]);

            Http::assertSent(fn (Request $request): bool => $request->url() === self::PROVIDER.'/api/'.$version.'/verify'
                && $request->hasHeader('Authorization', 'Bearer mock-provider-token')
                && $request->hasHeader('Accept', 'application/json')
                && $request->data() === $payload);
        }

        Http::assertSentCount(3);
    }

    public function test_validation_unavailable_routes_and_rotated_keys_never_reach_the_provider(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $expired = $this->issueKey($user);
        $key = $this->issueKey($user);
        $headers = ['Authorization' => 'Bearer '.$key];
        $body = ['type' => 'phone', 'idNumber' => '08012345678', 'consent' => true];

        $this->postJson(route('api.v1.phone'), $body)->assertUnauthorized();
        $this->postJson(route('api.v1.phone'), $body, ['Authorization' => 'Bearer '.$expired])->assertUnauthorized();
        $this->postJson(route('api.v1.phone'), ['type' => 'nin', 'idNumber' => 12345678901, 'consent' => false], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors(['type', 'idNumber', 'consent']);
        $this->postJson(route('api.v1.phone'), [...$body, 'idNumber' => '18012345678'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('idNumber');
        $this->postJson(route('api.v1.phone'), [...$body, 'idNumber' => '08012345678 '], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('idNumber');
        $this->postJson(route('api.v1.phone'), [...$body, 'consent' => 'true'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('consent');
        Http::assertNothingSent();
        $this->assertDatabaseCount('verification_requests', 0);
    }

    public function test_provider_verification_messages_are_forwarded_and_v1_docs_list_each_service(): void
    {
        $user = User::factory()->create();
        $key = $this->issueKey($user);
        Http::fake([
            self::PROVIDER.'/*' => Http::sequence()
                ->push(['status' => false, 'message' => 'No matching record'], 404)
                ->push(['status' => false, 'message' => ' '])
                ->push(['status' => false, 'message' => 'raw provider details'], 401)
                ->pushFailedConnection(),
        ]);

        foreach ([[422, 'No matching record'], [422, ' '], [502, 'raw provider details'], [503, 'Connection failed or timed out. Your balance has been restored.']] as [$status, $message]) {
            $response = $this->postJson(route('api.v1.nin'), [
                'type' => 'nin', 'idNumber' => '12345678901', 'consent' => true,
            ], ['Authorization' => 'Bearer '.$key])->assertStatus($status)->assertJsonPath('message', $message);
            $this->assertDatabaseHas('verification_requests', [
                'user_id' => $user->id, 'reference' => $response->json('transID'), 'status' => 'failed', 'result' => null,
            ]);
        }

        $this->get(route('portal.page', 'docs-v1'))->assertOk()
            ->assertSee(['/api/v1/nin', '/api/v1/phone', '/api/v1/bvn', '/api/personalization', '/api/clearance']);
    }

    public function test_v1_error_charge_toggle_applies_fees_without_overdrawing_the_account(): void
    {
        $user = User::factory()->create();
        $key = $this->issueKey($user);
        $this->get(route('admin.error-charge'))->assertForbidden();
        $this->post(route('admin.error-charge.save'), ['charge_errors' => '1'])->assertForbidden();

        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        $this->actingAs($admin)->get(route('admin.error-charge'))->assertOk()->assertSee('Error Charge');
        $this->post(route('admin.error-charge.save'), ['charge_errors' => '1'])->assertRedirect(route('admin.error-charge'));

        DB::table('service_prices')->insert(['service' => 'nin', 'version' => 'v1', 'price_minor' => 500, 'error_charge_minor' => 100]);
        DB::table('billing_entries')->insert(['user_id' => $user->id, 'description' => 'Credit', 'amount_minor' => 200]);

        Http::fake([self::PROVIDER.'/*' => Http::sequence()
            ->pushFailedConnection()
            ->push(['status' => false, 'message' => 'Insufficient balance.'])
            ->push(['status' => false, 'message' => 'Verification was unsuccessful.'])
            ->pushFailedConnection()]);

        $submit = fn () => $this->postJson(route('api.v1.nin'), [
            'type' => 'nin', 'idNumber' => '12345678901', 'consent' => true,
        ], ['Authorization' => 'Bearer '.$key]);
        $balance = fn () => (int) DB::table('billing_entries')->where('user_id', $user->id)->sum('amount_minor');

        $submit()->assertStatus(503)->assertJsonPath('message', 'Connection failed or timed out. An error charge was applied.');
        $this->assertSame(100, $balance());
        $submit()->assertStatus(402)->assertJsonPath('message', 'Insufficient balance.')->assertJsonPath('transID', ' ');
        $this->assertSame(100, $balance());
        $submit()->assertStatus(422)->assertJsonPath('message', 'Verification was unsuccessful.');
        $submit()->assertStatus(402)->assertJsonPath('transID', ' ');
        Http::assertSentCount(3);
        $this->assertSame(0, $balance());

        $this->post(route('admin.error-charge.save'), ['charge_errors' => '0'])->assertRedirect(route('admin.error-charge'));
        $submit()->assertStatus(503)->assertJsonPath('message', 'Connection failed or timed out. Your balance has been restored.');
        $this->assertSame(0, $balance());
        $this->assertDatabaseCount('billing_entries', 3);
    }

    public function test_service_documentation_is_separate_for_each_version_and_operation(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['v1', 'v2', 'v3'] as $version) {
            $index = $this->get(route('portal.page', 'docs-'.$version))->assertOk();
            $services = $version === 'v2' ? ['nin', 'phone', 'bvn'] : ['nin', 'phone', 'bvn', 'personalization', 'ipeclearing'];
            foreach ($services as $service) {
                $url = route('portal.docs.service', [$version, $service]);
                $index->assertSee($url);
                $page = $this->get($url)->assertOk()->assertSee('Example request body')->assertSee('Expected response');
                if (in_array($service, ['personalization', 'ipeclearing'], true)) {
                    $page->assertSee('Request submission')->assertSee('Check status');
                    if ($version !== 'v1') {
                        $page->assertSee('Contract pending');
                    }
                }
            }
        }

        $this->get(route('portal.docs.service', ['v1', 'phone']))->assertOk()
            ->assertSee('Authorization: Bearer YOUR_API_KEY')->assertSee('08012345678')->assertDontSee('nokState');
        $this->get(route('portal.docs.service', ['v1', 'nin']))->assertOk()->assertSee('nokState');
        $this->get(route('portal.docs.service', ['v3', 'phone']))->assertOk()->assertSee('Contract pending')->assertDontSee('08012345678');
        $this->get(route('portal.docs.service', ['v1', 'unknown']))->assertNotFound();
    }

    public function test_v2_nin_and_phone_use_the_same_provider_endpoint_and_record_v2_history(): void
    {
        $user = User::factory()->create();
        $key = $this->issueKey($user);
        $headers = ['Authorization' => 'Bearer '.$key];
        Http::fake([
            self::PROVIDER.'/api/v1/verify' => Http::response([
                'status' => true,
                'cached' => false,
                'message' => ['firstName' => 'EXAMPLE'],
            ]),
        ]);

        DB::table('verification_settings')->insert(['version' => 'v1', 'charge_errors' => true]);
        DB::table('service_prices')->insert(['service' => 'nin', 'version' => 'v1', 'price_minor' => 500, 'error_charge_minor' => 100]);

        foreach (['nin' => '12345678901', 'phone' => '08012345678'] as $type => $number) {
            $payload = ['type' => $type, 'idNumber' => $number, 'consent' => true];
            $response = $this->postJson(route('api.v2.'.$type), $payload, $headers)
                ->assertOk()->assertJsonPath('status', true)->assertJsonPath('message.firstName', 'EXAMPLE');

            Http::assertSent(fn (Request $request): bool => $request->url() === self::PROVIDER.'/api/v1/verify'
                && $request->hasHeader('Authorization', 'Bearer mock-provider-token') && $request->data() === $payload);
            $this->assertDatabaseHas('verification_requests', [
                'user_id' => $user->id, 'reference' => $response->json('transID'), 'version' => 'v2', 'service' => $type,
            ]);

            $this->get(route('portal.docs.service', ['v2', $type]))->assertOk()->assertSee('POST '.rtrim(config('app.url'), '/').'/api/v2/'.$type);
        }

        $this->postJson('/api/v2/nin', ['type' => 'phone', 'idNumber' => '12345678901', 'consent' => true], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('type');
        Http::assertSentCount(2);
    }

    public function test_site_branding_and_documented_endpoints_use_app_configuration(): void
    {
        config(['app.name' => 'Example Portal', 'app.url' => 'https://verify.example.test/gateway/']);

        $this->get(route('login'))->assertOk()->assertSee('Log in to your Example Portal account.');
        $this->actingAs(User::factory()->create());

        $this->get(route('portal.page', 'docs-v1'))->assertOk()
            ->assertSee('Example Portal')->assertSee('POST https://verify.example.test/gateway/api/v1/nin')
            ->assertSee('POST https://verify.example.test/gateway/api/clearance/status');
        $this->get(route('portal.docs.service', ['v2', 'phone']))->assertOk()
            ->assertSee('POST https://verify.example.test/gateway/api/v2/phone')->assertSee('Use your Example Portal API key');
    }

    public function test_bvn_by_phone_forwards_the_provider_contract(): void
    {
        $user = User::factory()->create();
        $headers = ['Authorization' => 'Bearer '.$this->issueKey($user)];
        $payload = ['type' => 'bvn_with_phone', 'idNumber' => '08000000000', 'consent' => true];
        $identity = ['firstName' => 'EXAMPLE', 'idNumber' => '12345678901', 'bvn' => '12345678901', 'phoneNumber1' => '08000000000',
            'email' => 'user@example.com', 'enrollmentBank' => 'EXAMPLE BANK', 'stateOfResidence' => ' ', 'watchListed' => 'NO', 'base64Image' => 'BASE64_PHOTO'];
        Http::fake([self::PROVIDER.'/api/v2/verify' => Http::response(['status' => true, 'cached' => false, 'message' => $identity])]);

        $response = $this->postJson(route('api.v1.bvn-by-phone'), $payload, $headers)->assertOk()
            ->assertJsonPath('status', true)->assertJsonFragment($identity);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::PROVIDER.'/api/v2/verify'
            && $request->hasHeader('Authorization', 'Bearer mock-provider-token') && $request->data() === $payload);
        $this->assertDatabaseHas('verification_requests', [
            'user_id' => $user->id, 'service' => 'bvn-by-phone', 'version' => 'v1', 'reference' => $response->json('transID'), 'result' => null,
        ]);

        $this->postJson(route('api.v1.bvn-by-phone'), [...$payload, 'idNumber' => '18000000000'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('idNumber');
        Http::assertSentCount(1);
        $this->get(route('portal.docs.service', ['v1', 'bvn-by-phone']))->assertOk()->assertSee(['bvn_with_phone', 'phoneNumber1']);
    }

    public function test_v2_bvn_uses_the_expanded_bvn_provider_contract(): void
    {
        $user = User::factory()->create();
        $headers = ['Authorization' => 'Bearer '.$this->issueKey($user)];
        $payload = ['type' => 'bvn', 'idNumber' => '12345678901', 'consent' => true];
        $identity = ['firstName' => 'EXAMPLE', 'idNumber' => '12345678901', 'bvn' => '12345678901', 'phoneNumber1' => '08000000000',
            'email' => 'user@example.com', 'enrollmentBank' => 'EXAMPLE BANK', 'stateOfResidence' => ' ', 'watchListed' => 'NO', 'base64Image' => 'BASE64_PHOTO'];
        Http::fake([self::PROVIDER.'/api/v2/verify' => Http::response(['status' => true, 'cached' => false, 'message' => $identity])]);

        $response = $this->postJson(route('api.v2.bvn'), $payload, $headers)->assertOk()->assertJsonFragment($identity);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::PROVIDER.'/api/v2/verify'
            && $request->hasHeader('Authorization', 'Bearer mock-provider-token') && $request->data() === $payload);
        $this->assertDatabaseHas('verification_requests', [
            'user_id' => $user->id, 'service' => 'bvn', 'version' => 'v2', 'reference' => $response->json('transID'), 'result' => null,
        ]);
        $this->get(route('portal.docs.service', ['v2', 'bvn']))->assertOk()->assertSee(['/api/v2/bvn', 'phoneNumber1']);
    }
}
