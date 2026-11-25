<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admins_can_create_edit_and_search_users_and_set_prices(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.users.index'))->assertForbidden();
        $this->post(route('admin.users.store'))->assertForbidden();

        $this->actingAs($this->superAdmin())->get(route('admin.users.create'))->assertOk();
        $profile = ['name' => 'Example Client', 'email' => 'CLIENT@example.test', 'business_name' => 'Example Company',
            'phone' => '+234 800 123 4567', 'role' => 'admin', 'account_status' => 'active'];
        $this->post(route('admin.users.store'), [
            ...$profile, 'password' => 'strong-password', 'password_confirmation' => 'strong-password',
        ])->assertRedirect();
        $user = User::where('email', 'client@example.test')->sole();
        $this->assertTrue(Hash::check('strong-password', $user->password));
        $this->assertSame('admin', $user->role);
        $this->assertSame('+2348001234567', $user->phone);

        $price = DB::table('service_prices')->insertGetId(['service' => 'nin', 'version' => 'v2', 'price_minor' => 30000]);
        $this->put(route('admin.users.update', $user), [...$profile, 'role' => 'user', 'prices' => [$price => '123.45']])->assertRedirect();
        $this->assertDatabaseHas('user_service_prices', ['user_id' => $user->id, 'service_price_id' => $price, 'price_minor' => 12345]);
        $this->get(route('admin.users.edit', $user))->assertOk()->assertSee('123.45');
        $this->get(route('admin.users.index', ['q' => 'client@']))->assertOk()
            ->assertSee(['Example Client', 'Example Company', 'Unverified']);
        $this->actingAs($user->fresh())->get(route('portal.pricing'))->assertOk()->assertSee('₦123.45')->assertDontSee('₦300.00');
    }

    public function test_status_and_password_changes_revoke_sessions_and_archive_preserves_history(): void
    {
        $super = $this->superAdmin();
        $user = User::factory()->create();
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert(['id' => 'revoked-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($super)->post(route('admin.users.status', $super), ['action' => 'archive'])->assertSessionHasErrors('role');
        $this->post(route('admin.users.status', $user), ['action' => 'suspend'])->assertRedirect();
        $this->assertDatabaseMissing('sessions', ['id' => 'revoked-session']);
        $this->actingAs($user->fresh())->get(route('account'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post(route('login.store'), ['identifier' => $user->email, 'password' => 'password'])->assertSessionHasErrors('identifier');

        $this->actingAs($super)->post(route('admin.users.status', $user), ['action' => 'reactivate'])->assertRedirect();
        $this->post(route('admin.users.password', $user), ['password' => 'new-password', 'password_confirmation' => 'new-password'])->assertRedirect();
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertSame(2, (int) $user->fresh()->session_version);
        $this->actingAs($user->fresh())->withSession(['account_session_version' => 1])->get(route('account'))->assertRedirect(route('login'));

        $this->actingAs($super)->post(route('admin.users.balance', $user), ['transaction_type' => 'credit', 'amount' => '10', 'reason' => 'Archive test'])->assertRedirect();
        $this->post(route('admin.users.status', $user), ['action' => 'archive'])->assertRedirect();
        $this->get(route('admin.users.index', ['q' => $user->email]))->assertOk()->assertSee(['Archived', $user->email, '₦10.00']);
        $this->post(route('admin.users.status', $user), ['action' => 'restore'])->assertRedirect();
        $this->assertSame('active', $user->fresh()->account_status);
        $this->assertDatabaseHas('billing_entries', ['user_id' => $user->id, 'description' => 'Archive test', 'amount_minor' => 1000]);
    }

    public function test_balance_history_and_verification_controls(): void
    {
        Notification::fake();
        $super = $this->superAdmin();
        $user = User::factory()->unverified()->create();
        $this->actingAs($super)->post(route('admin.users.verification', $user))->assertRedirect();
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->post(route('admin.users.balance', $user), ['transaction_type' => 'credit', 'amount' => '100.25', 'reason' => 'Initial funding'])->assertRedirect();
        $this->post(route('admin.users.balance', $user), ['transaction_type' => 'debit', 'amount' => '20.10', 'reason' => 'Correction'])->assertRedirect();
        $this->post(route('admin.users.balance', $user), ['transaction_type' => 'debit', 'amount' => '80.16', 'reason' => 'Overdraft'])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('billing_entries', 2);
        $this->assertDatabaseHas('billing_entries', ['user_id' => $user->id, 'actor_id' => $super->id, 'description' => 'Correction', 'amount_minor' => -2010]);
        $this->get(route('admin.users.edit', $user))->assertOk()->assertSee(['Balance history', 'Initial funding', 'Correction', '₦100.25', '₦80.15']);

        $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(5), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->actingAs($user)->get($link)->assertRedirect(route('account'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->actingAs($super)->post(route('admin.users.verification', $user))->assertSessionHasErrors('email');
    }

    public function test_individual_pricing_saves_and_removes_overrides_without_a_standard_price(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($this->superAdmin())->get(route('admin.users.edit', $customer))->assertOk()
            ->assertSee(['Individual service prices', 'NIN', 'IPE clearing', 'Instant-ipe', 'Not set']);

        $this->put(route('admin.users.prices', $customer), ['new_prices' => ['v2' => ['nin' => '90.50']]])->assertRedirect();
        $price = DB::table('service_prices')->where('service', 'nin')->where('version', 'v2')->sole();
        $this->assertNull($price->price_minor);
        $this->assertFalse((bool) $price->available);
        $this->assertDatabaseHas('user_service_prices', [
            'user_id' => $customer->id, 'service_price_id' => $price->id, 'price_minor' => 9050,
        ]);
        $this->get(route('admin.users.edit', $customer))->assertOk()->assertSee('90.50');
        $this->put(route('admin.users.prices', $customer), ['prices' => [$price->id => '']])->assertRedirect();
        $this->assertDatabaseMissing('user_service_prices', ['user_id' => $customer->id, 'service_price_id' => $price->id]);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => 'super_admin']);
    }
}
