<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_register_and_sign_back_in_by_phone_or_email(): void
    {
        $this->get(route('account'))->assertRedirect(route('login'));
        $this->get(route('register'))->assertOk()->assertSee('First name');

        $this->post(route('register.store'), [
            'first_name' => 'Amina',
            'last_name' => 'Okafor',
            'email' => 'Amina@example.com',
            'phone' => '+234 (800) 123-4567',
            'password' => 'example-password',
        ])->assertRedirect(route('account'));

        $user = User::where('email', 'amina@example.com')->firstOrFail();
        $this->assertSame('Amina Okafor', $user->name);
        $this->assertSame('+2348001234567', $user->phone);
        $this->assertAuthenticatedAs($user);

        foreach (['+234 800 123 4567', 'AMINA@example.com'] as $identifier) {
            $this->post(route('logout'))->assertRedirect(route('home'));
            $this->assertGuest();

            $this->post(route('login.store'), [
                'identifier' => $identifier,
                'password' => 'example-password',
            ])->assertRedirect(route('account'));

            $this->assertAuthenticatedAs($user);
        }

        $this->get(route('login'))->assertRedirect(route('account'));
    }

    public function test_invalid_registration_cannot_create_an_account(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->from(route('register'))->post(route('register.store'), [
            'first_name' => 'Amina',
            'last_name' => 'Okafor',
            'email' => 'TAKEN@example.com',
            'phone' => 'invalid',
            'password' => 'short',
        ])->assertRedirect(route('register'))->assertSessionHasErrors(['email', 'phone', 'password']);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_failed_login_attempts_are_throttled(): void
    {
        $user = User::factory()->create(['phone' => '+2348001234567']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), [
                'identifier' => $user->phone,
                'password' => 'incorrect-password',
            ])->assertSessionHasErrors(['identifier' => __('auth.failed')]);
        }

        $this->post(route('login.store'), [
            'identifier' => '+234 800 123 4567',
            'password' => 'password',
        ])->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }
}
