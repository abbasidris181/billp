<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): RedirectResponse
    {
        $identifier = Str::of($request->validated('identifier'))->lower();
        $field = $identifier->contains('@') ? 'email' : 'phone';

        if ($field === 'phone') {
            $identifier = $identifier->replaceMatches('/[\s().-]+/', '');
        }

        $key = 'login:'.hash('sha256', $identifier.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'identifier' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $password = $request->validated('password');

        if (str_contains($password, "\0") || ! Auth::attempt([$field => $identifier->toString(), 'password' => $password, 'account_status' => 'active'])) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['identifier' => __('auth.failed')]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put('account_session_version', (int) Auth::user()->session_version);

        return redirect()->intended(route('account'));
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $user = User::create([
                'name' => $data['first_name'].' '.$data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
            ]);
        } catch (UniqueConstraintViolationException) {
            foreach (['email', 'phone'] as $field) {
                if (User::where($field, $data[$field])->exists()) {
                    throw ValidationException::withMessages([$field => __('validation.unique', ['attribute' => $field])]);
                }
            }

            throw ValidationException::withMessages(['email' => 'An account with these details already exists.']);
        }

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('account_session_version', (int) Auth::user()->session_version);

        return to_route('account');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home');
    }
}
