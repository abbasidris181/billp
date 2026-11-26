<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSignupEmailVerification
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->needsSignupEmailVerification()) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Your email address is not verified.'], 403)
            : to_route('verification.notice');
    }
}
