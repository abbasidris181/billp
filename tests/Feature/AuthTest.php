<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_register_and_sign_back_in_by_phone_or_email(): void
    {
        Notification::fake();
        $this->get(route('account'))->assertRedirect(route('login'));
        $this->get(route('register'))->assertOk()->assertSee('First name');

        $this->post(route('register.store'), [
            'first_name' => 'Amina',
            'last_name' => 'Okafor',
            'email' => 'Amina@example.com',
            'phone' => '+234 (800) 123-4567',
            'password' => 'example-password',
            'password_confirmation' => 'example-password',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'amina@example.com')->firstOrFail();
        $this->assertSame('Amina Okafor', $user->name);
        $this->assertSame('+2348001234567', $user->phone);
        $this->assertAuthenticatedAs($user);

        Notification::assertSentTo($user, VerifyEmail::class);
        $this->get(route('account'))->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))->assertOk()->assertSee($user->email);
        $this->post(route('verification.send'))->assertRedirect(route('verification.notice'))->assertSessionHas('verification_sent', true);
        Notification::assertSentToTimes($user, VerifyEmail::class, 2);

        $this->get(route('verification.notice'))->assertOk()->assertSee('A new verification link was sent.');
        $this->get(route('verification.notice'))->assertOk()->assertSee('Verify your email')->assertDontSee('A new verification link was sent.');
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->post(route('login.store'), [
            'identifier' => $user->email,
            'password' => 'example-password',
        ])->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))->assertOk()->assertSee($user->email);

        $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(5), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);
        $this->get($link)->assertRedirect(route('account'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get(route('account'))->assertOk();

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

    public function test_signup_and_resend_keep_the_account_accessible_when_smtp_fails(): void
    {
        Notification::shouldReceive('send')->twice()->andThrow(new TransportException('SMTP authentication failed.'));

        $this->post(route('register.store'), [
            'first_name' => 'Amina',
            'last_name' => 'Okafor',
            'email' => 'amina@example.com',
            'phone' => '+2348001234567',
            'password' => 'example-password',
            'password_confirmation' => 'example-password',
        ])->assertRedirect(route('verification.notice'))->assertSessionHas('verification_delivery_failed', true);
        $user = User::where('email', 'amina@example.com')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->post(route('verification.send'))->assertRedirect(route('verification.notice'))
            ->assertSessionHas('verification_delivery_failed', true)->assertSessionMissing('verification_sent');
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

    public function test_signup_requires_matching_passwords(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('Confirm password');
        $this->post(route('register.store'), [
            'first_name' => 'Amina', 'last_name' => 'Okafor', 'email' => 'amina@example.com',
            'phone' => '+2348001234567', 'password' => 'example-password', 'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_forgot_password_sends_a_link_and_resets_the_password_once(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->get(route('login'))->assertOk()->assertSee('Forgot password?');
        $this->post(route('password.email'), ['email' => strtoupper($user->email)])->assertRedirect(route('password.request'));

        $notification = Notification::sent($user, ResetPassword::class)->sole();
        $url = route('password.reset', ['token' => $notification->token, 'email' => $user->email]);
        $this->get($url)->assertOk()->assertSee($user->email)->assertSee('Confirm new password');
        $body = [
            'email' => $user->email, 'token' => $notification->token,
            'password' => 'replacement-password', 'password_confirmation' => 'replacement-password',
        ];
        $this->from($url)->post(route('password.update'), [...$body, 'password_confirmation' => 'other-password'])
            ->assertRedirect($url)->assertSessionHasErrors('password');
        $this->post(route('password.update'), $body)->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('replacement-password', $user->fresh()->password));
        $this->assertSame(1, (int) $user->fresh()->session_version);
        $this->from($url)->post(route('password.update'), $body)->assertRedirect($url)->assertSessionHasErrors('email');
    }

    public function test_existing_unverified_users_can_sign_in_without_verifying(): void
    {
        $user = User::factory()->unverified()->create();

        $this->post(route('login.store'), ['identifier' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('account'));
        $this->get(route('account'))->assertOk();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
