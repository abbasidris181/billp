<?php

namespace App\Http\Controllers;

use App\Models\User;
use Closure;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        try {
            Password::sendResetLink(['email' => strtolower($data['email']), 'account_status' => 'active']);
        } catch (TransportExceptionInterface $exception) {
            report($exception);
        }

        return to_route('password.request')->with('status', 'If an active account matches that email address, we’ll send a password reset link.');
    }

    public function edit(Request $request, string $token): View
    {
        $email = $request->query('email');

        return view('auth.reset-password', ['token' => $token, 'email' => is_string($email) ? $email : '']);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => [
                'bail', 'required', 'string', 'confirmed', PasswordRule::min(8),
                function (string $attribute, string $value, Closure $fail): void {
                    if (strlen($value) > 72 || str_contains($value, "\0")) {
                        $fail('Use a password of up to 72 bytes without null characters.');
                    }
                },
            ],
        ]);

        $status = Password::reset(
            [...$data, 'email' => strtolower($data['email']), 'account_status' => 'active'],
            function (User $user, string $password): void {
                DB::transaction(function () use ($user, $password): void {
                    $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                    $locked->password = $password;
                    $locked->remember_token = null;
                    $locked->session_version++;
                    $locked->save();

                    if (config('session.driver') === 'database') {
                        DB::connection(config('session.connection'))->table(config('session.table'))->where('user_id', $locked->id)->delete();
                    }
                }, 3);

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'This password reset link is invalid or has expired.']);
        }

        return to_route('login')->with('status', 'Password reset. Sign in with your new password.');
    }
}
