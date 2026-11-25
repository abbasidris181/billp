<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_search_export_results_and_billing_are_account_scoped(): void
    {
        [$owner, $other] = User::factory()->count(2)->create();
        $this->actingAs($owner);

        $requestIds = [];
        foreach ([$owner, $other] as $user) {
            $requestIds[] = DB::table('verification_requests')->insertGetId([
                'user_id' => $user->id,
                'reference' => $user->is($owner) ? '=owned-reference' : 'other-reference',
                'service' => 'Identity',
                'version' => 'v2',
                'status' => 'successful',
                'result' => json_encode(['verified' => true]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('billing_entries')->insert([
            ['user_id' => $owner->id, 'description' => 'Credit received', 'amount_minor' => 140000, 'created_at' => now()],
            ['user_id' => $owner->id, 'description' => 'Verification charge', 'amount_minor' => -25000, 'created_at' => now()],
            ['user_id' => $other->id, 'description' => 'Other account credit', 'amount_minor' => 90000, 'created_at' => now()],
        ]);

        $this->get(route('account'))->assertOk()->assertSee('₦1,150.00')->assertSee('Credit received')->assertDontSee('other-reference')->assertDontSee('Admin Dashboard');
        $this->get(route('portal.billing'))->assertOk()->assertSee('Verification charge')->assertDontSee('Other account credit');
        $this->get(route('portal.requests', ['status' => 'successful', 'version' => 'v2', 'to' => today()->toDateString()]))
            ->assertOk()->assertSee('=owned-reference')->assertDontSee('other-reference');
        $this->get(route('portal.requests', ['q' => 'missing']))->assertOk()->assertSee('No verification requests found.');
        $this->get(route('portal.result', $requestIds[0]))->assertOk()->assertSee('verified');
        $this->get(route('portal.result', $requestIds[1]))->assertNotFound();
        $export = $this->get(route('portal.export', ['service' => 'Identity']))->assertDownload('verification-requests.csv')->streamedContent();
        $this->assertStringContainsString("'=owned-reference", $export);
        $this->assertStringNotContainsString('other-reference', $export);
    }

    public function test_api_keys_can_be_replaced_and_are_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('portal.api'))->assertOk()->assertSee('Generate an API key');
        $this->post(route('portal.key'))->assertRedirect(route('portal.api'));
        $firstKey = $user->fresh()->api_key;
        $this->assertStringStartsWith('billp_', $firstKey);
        $this->assertNotSame($firstKey, DB::table('users')->where('id', $user->id)->value('api_key'));

        $this->post(route('portal.key'))->assertRedirect(route('portal.api'));
        $this->assertNotSame($firstKey, $user->fresh()->api_key);
        $this->get(route('portal.api'))->assertOk()->assertSee($user->fresh()->api_key)->assertDontSee($firstKey);
    }

    public function test_admins_can_review_private_documents_and_publish_pricing_and_docs(): void
    {
        Storage::fake('local');
        [$customer, $admin] = User::factory()->count(2)->create();
        $admin->is_admin = true;
        $admin->save();

        $this->actingAs($customer)->get(route('admin.index'))->assertForbidden();
        $this->get(route('portal.access'))->assertOk()->assertSee('Business verification');
        $this->post(route('portal.apply'), [
            'business_name' => 'Example Verification',
            'registration_number' => 'RC1234',
            'use_case' => 'Validate customer identity.',
            'document' => UploadedFile::fake()->create('registration.pdf', 10, 'application/pdf'),
        ])->assertRedirect(route('portal.access'));

        $application = DB::table('live_access_applications')->where('user_id', $customer->id)->sole();
        Storage::disk('local')->assertExists($application->document_path);
        $this->get(route('admin.document', $application->id))->assertForbidden();
        $this->get(route('portal.access'))->assertOk()->assertSee('Pending');

        $this->actingAs($admin)->get(route('admin.index'))->assertOk()->assertSee('Pending applications');
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.records', 'requests'))->assertOk();
        $this->get(route('admin.records', 'applications'))->assertOk()->assertSee('RC1234');
        $this->get(route('admin.document', $application->id))->assertDownload();
        $this->post(route('admin.review', $application->id), ['status' => 'approved'])->assertRedirect(route('admin.records', 'applications'));

        $this->get(route('admin.catalog'))->assertOk()->assertSee(['NIN', 'Phone', 'BVN', 'Personalization', 'IPE clearing', 'V1', 'V2', 'Not set']);
        $this->post(route('admin.price'), [
            'service' => 'Identity',
            'version' => 'v3',
            'price_minor' => 20000,
            'error_charge_minor' => 1000,
            'available' => '1',
        ])->assertRedirect(route('admin.catalog'));
        $this->get(route('admin.catalog'))->assertOk()->assertSee(['Identity', 'V3', '₦200.00']);
        $this->get(route('admin.content', 'docs'))->assertOk();
        $this->post(route('admin.publish', 'docs'), ['slug' => 'v3', 'title' => 'Version three', 'body' => '<script>Example</script>'])
            ->assertRedirect(route('admin.content', 'docs'));
        $this->get(route('admin.content', 'docs'))->assertOk()->assertSee('Version three');

        $this->actingAs($customer)->get(route('portal.access'))->assertOk()->assertSee('Approved');
        $this->get(route('portal.pricing'))->assertOk()->assertSee('₦200.00')->assertSee('Available');
        $this->get(route('portal.page', 'docs-v3'))->assertOk()->assertSee('<script>Example</script>')->assertDontSee('<script>Example</script>', false);
    }
}
