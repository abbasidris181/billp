<?php

namespace App\Http\Clients;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class TrackingProvider
{
    /** @param array<string, int|string> $data */
    public function post(string $path, array $data): ?Response
    {
        $url = config('services.clearance.url');
        $token = config('services.clearance.token');

        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)
            || parse_url($url, PHP_URL_SCHEME) !== 'https' || ! is_string($token) || blank($token)) {
            return null;
        }

        return Http::acceptJson()->asJson()->withoutRedirecting()->connectTimeout(5)->timeout(30)
            ->post(rtrim($url, '/').$path, ['token' => $token, ...$data]);
    }
}
