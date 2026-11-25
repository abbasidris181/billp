<?php

namespace App\Http\Clients;

use App\Http\Requests\VerifyIdentityRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class VerificationProvider
{
    private const string INSUFFICIENT_BALANCE = 'Insufficient balance.';

    private const string TIMEOUT_RESTORED = 'Connection failed or timed out. Your balance has been restored.';

    private const array IDENTITY_FIELDS = [
        'firstName', 'middleName', 'lastName', 'mobile', 'dateOfBirth', 'gender', 'address',
        'birthCountry', 'birthState', 'birthLGA', 'title', 'centralID', 'educationallevel',
        'employmentstatus', 'height', 'maritalstatus', 'nok_address1', 'nok_firstname',
        'photo', 'signature', 'idNumber', 'trackingId',
    ];

    public function verify(VerifyIdentityRequest $request, string $path): JsonResponse
    {
        $data = $request->validated();
        $baseUrl = config('services.verification.url');
        $token = config('services.verification.token');

        if (! is_string($baseUrl) || ! filter_var($baseUrl, FILTER_VALIDATE_URL)
            || parse_url($baseUrl, PHP_URL_SCHEME) !== 'https' || ! is_string($token) || blank($token)) {
            return response()->json(['status' => false, 'message' => 'Verification is currently unavailable.'], 503);
        }

        $fee = $request->apiVersion() === 'v1' ? $this->errorFee($data['type']) : 0;

        if ($fee > 0 && $this->balance($request->user()->id) < $fee) {
            return $this->failure(' ', self::INSUFFICIENT_BALANCE, 402);
        }

        $reference = (string) Str::uuid();

        try {
            $response = Http::acceptJson()->asJson()->withToken($token)
                ->withoutRedirecting()->connectTimeout(5)->timeout(30)
                ->post(rtrim($baseUrl, '/').$path, $data);
        } catch (ConnectionException) {
            return $this->connectionFailure($request, $reference, $fee);
        }

        $payload = $response->json();

        $message = data_get($payload, 'message');

        if (in_array($response->status(), [408, 504], true) || $message === self::TIMEOUT_RESTORED) {
            return $this->connectionFailure($request, $reference, $fee);
        }

        if (data_get($payload, 'status') === false) {
            if ($message === self::INSUFFICIENT_BALANCE) {
                $this->record($request, $reference, 'failed');

                return $this->failure(' ', self::INSUFFICIENT_BALANCE, 402);
            }

            if (! $this->recordFailure($request, $reference, $fee)) {
                return $this->failure(' ', self::INSUFFICIENT_BALANCE, 402);
            }

            $noMatch = $response->successful() || in_array($response->status(), [404, 422], true);
            $fallback = $noMatch ? 'Verification was unsuccessful.' : 'The verification provider returned an invalid response.';

            return $this->failure($reference, is_string($message) ? $message : $fallback, $noMatch ? 422 : 502);
        }

        $identity = data_get($payload, 'message');

        if (! $response->successful() || data_get($payload, 'status') !== true || ! is_array($identity)
            || ! array_intersect_key($identity, array_flip(['firstName', 'lastName', 'idNumber']))) {
            $this->record($request, $reference, 'failed');

            return $this->failure($reference, 'The verification provider returned an invalid response.', 502);
        }

        $this->record($request, $reference, 'successful');

        $fields = $data['type'] === 'nin'
            ? [...self::IDENTITY_FIELDS, 'nokState', 'religion', 'status', 'isConsent', 'type', 'country']
            : self::IDENTITY_FIELDS;
        $identity = Arr::only($identity, $fields);

        if (is_array($identity['address'] ?? null)) {
            $identity['address'] = (object) Arr::only($identity['address'], ['state', 'town', 'addressLine', 'lga', 'city']);
        }

        return response()->json([
            'status' => true,
            'cached' => data_get($payload, 'cached') === true,
            'message' => (object) $identity,
            'transID' => $reference,
        ]);
    }

    private function errorFee(string $service): int
    {
        if (! DB::table('verification_settings')->where('version', 'v1')->value('charge_errors')) {
            return 0;
        }

        return (int) DB::table('service_prices')->where('service', $service)->where('version', 'v1')->value('error_charge_minor');
    }

    private function balance(int $userId): int
    {
        return (int) DB::table('billing_entries')->where('user_id', $userId)->sum('amount_minor');
    }

    private function connectionFailure(VerifyIdentityRequest $request, string $reference, int $fee): JsonResponse
    {
        if (! $this->recordFailure($request, $reference, $fee)) {
            return $this->failure(' ', self::INSUFFICIENT_BALANCE, 402);
        }

        $message = $fee > 0 ? 'Connection failed or timed out. An error charge was applied.' : self::TIMEOUT_RESTORED;

        return $this->failure($reference, $message, 503);
    }

    private function recordFailure(VerifyIdentityRequest $request, string $reference, int $fee): bool
    {
        if ($fee === 0) {
            $this->record($request, $reference, 'failed');

            return true;
        }

        return DB::transaction(function () use ($request, $reference, $fee): bool {
            $userId = $request->user()->id;
            DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            $this->record($request, $reference, 'failed');

            if ($this->balance($userId) < $fee) {
                return false;
            }

            DB::table('billing_entries')->insert([
                'user_id' => $userId,
                'description' => 'V1 '.strtoupper($request->validated('type')).' error charge · '.$reference,
                'amount_minor' => -$fee,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        }, 3);
    }

    private function record(VerifyIdentityRequest $request, string $reference, string $status): void
    {
        DB::table('verification_requests')->insert([
            'user_id' => $request->user()->id,
            'reference' => $reference,
            'service' => $request->validated('type'),
            'version' => $request->apiVersion(),
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function failure(string $reference, string $message, int $status): JsonResponse
    {
        return response()->json([
            'status' => false,
            'cached' => false,
            'message' => $message,
            'transID' => $reference,
        ], $status);
    }
}
