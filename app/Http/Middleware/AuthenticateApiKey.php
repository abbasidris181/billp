<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next, string $source = 'bearer'): Response
    {
        $token = $source === 'body' ? $request->json('token') : $request->bearerToken();
        $user = is_string($token) && preg_match('/\Abillp_[A-Za-z0-9]{48}\z/', $token)
            ? User::where('api_key_selector', User::apiKeySelector($token))->get()
                ->first(fn (User $candidate): bool => hash_equals((string) $candidate->api_key, $token))
            : null;

        if ($user === null || ! $user->isActive()) {
            if ($source === 'body') {
                return response()->json(['error' => 'A valid '.config('app.name').' API key is required.'], 401, ['Cache-Control' => 'no-store']);
            }

            return response()->json(['status' => false, 'message' => 'A valid '.config('app.name').' API key is required.'], 401, [
                'WWW-Authenticate' => 'Bearer',
                'Cache-Control' => 'no-store',
            ]);
        }

        $request->setUserResolver(fn (): User => $user);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
