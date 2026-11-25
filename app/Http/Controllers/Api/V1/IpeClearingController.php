<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Clients\TrackingProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClearanceRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class IpeClearingController extends Controller
{
    public function __construct(private TrackingProvider $provider) {}

    public function submit(ClearanceRequest $request): JsonResponse
    {
        $trackingId = $request->validated('tracking_id');
        $digest = $this->digest($trackingId);
        $userId = $request->user()->id;
        $owner = $this->owner($digest);

        if ($owner !== null && $owner !== $userId) {
            return $this->duplicate();
        }

        $response = $this->send('/api/clearance', $trackingId);

        if ($response instanceof JsonResponse) {
            return $response;
        }

        $message = $response->json('success');

        if (! $response->successful() || ! is_string($message)) {
            return $this->providerError($response);
        }

        DB::table('clearance_submissions')->insertOrIgnore(['tracking_digest' => $digest, 'user_id' => $userId]);

        if ($this->owner($digest) !== $userId) {
            return $this->duplicate();
        }

        return response()->json(['success' => $message], $response->status());
    }

    public function status(ClearanceRequest $request): JsonResponse
    {
        $trackingId = $request->validated('tracking_id');

        if ($this->owner($this->digest($trackingId)) !== $request->user()->id) {
            return response()->json(['error' => 'Clearance record not found.'], 404);
        }

        $response = $this->send('/api/clearance/status', $trackingId);

        if ($response instanceof JsonResponse) {
            return $response;
        }

        $result = $response->json();

        if ($response->redirect() || ! is_array($result) || ! is_string($result['status'] ?? null)) {
            return $this->providerError($response);
        }

        return response()->json([
            'status' => $result['status'],
            'reply' => $result['reply'] ?? null,
            'nin' => $result['nin'] ?? null,
            'name' => $result['name'] ?? null,
            'dob' => $result['dob'] ?? null,
        ], $response->status());
    }

    private function send(string $path, string $trackingId): Response|JsonResponse
    {
        try {
            return $this->provider->post($path, ['tracking_id' => $trackingId])
                ?? response()->json(['error' => 'Clearance is currently unavailable.'], 503);
        } catch (ConnectionException) {
            return response()->json(['error' => 'The clearance provider could not be reached.'], 503);
        }
    }

    private function providerError(Response $response): JsonResponse
    {
        $message = $response->json('error');

        if (is_string($message) && ($response->successful() || $response->clientError() || $response->serverError())) {
            return response()->json(['error' => $message], $response->successful() ? 400 : $response->status());
        }

        return response()->json(['error' => 'The clearance provider returned an invalid response.'], 502);
    }

    private function digest(string $trackingId): string
    {
        return hash('sha256', strtoupper($trackingId));
    }

    private function owner(string $digest): ?int
    {
        $owner = DB::table('clearance_submissions')->where('tracking_digest', $digest)->value('user_id');

        return $owner === null ? null : (int) $owner;
    }

    private function duplicate(): JsonResponse
    {
        return response()->json(['error' => 'Duplicate Entry detected. Attempt saved as Failed.'], 400);
    }
}
