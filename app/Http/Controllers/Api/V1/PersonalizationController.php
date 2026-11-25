<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Clients\TrackingProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckPersonalizationRequest;
use App\Http\Requests\SubmitPersonalizationRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use stdClass;

class PersonalizationController extends Controller
{
    public function __construct(private TrackingProvider $provider) {}

    public function submit(SubmitPersonalizationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $digest = $this->digest($data['tracking_id']);
        $userId = $request->user()->id;
        $owner = $this->owner($digest);

        if ($owner !== null && $owner !== $userId) {
            return $this->notFound();
        }

        $response = $this->send('/api/personalization', $data);
        if ($response instanceof JsonResponse) {
            return $response;
        }

        $payload = $this->payload($response);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        if ($payload->success === false) {
            return response()->json($payload, $response->status());
        }

        if (! $this->matchesTracking($payload, $digest)) {
            return $this->invalidResponse();
        }

        $saved = DB::transaction(function () use ($digest, $userId, $payload): bool {
            $created = DB::table('personalization_tracking')->insertOrIgnore(['tracking_digest' => $digest, 'user_id' => $userId]);
            if ($this->owner($digest) !== $userId) {
                return false;
            }

            DB::table('personalization_submissions')->insertOrIgnore([
                'provider_id' => $payload->id,
                'tracking_digest' => $digest,
            ]);

            $saved = $this->hasSubmission($payload->id, $digest);
            if (! $saved && $created) {
                DB::table('personalization_tracking')->where('tracking_digest', $digest)->delete();
            }

            return $saved;
        }, 3);

        return $saved ? response()->json($payload, $response->status()) : $this->notFound();
    }

    public function check(CheckPersonalizationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $submissionId = $data['id'] ?? null;
        $digest = isset($data['tracking_id'])
            ? $this->digest($data['tracking_id'])
            : DB::table('personalization_submissions')->where('provider_id', $submissionId)->value('tracking_digest');

        if ($digest === null || $this->owner($digest) !== $request->user()->id) {
            return $this->notFound();
        }

        $response = $this->send('/api/personalization/check', $data);
        if ($response instanceof JsonResponse) {
            return $response;
        }

        $payload = $this->payload($response);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        if ($payload->success && (! $this->matchesTracking($payload, $digest)
            || ($submissionId !== null && $payload->id !== $submissionId)
            || ! $this->hasSubmission($payload->id, $digest))) {
            return $this->invalidResponse();
        }

        return response()->json($payload, $response->status());
    }

    /** @param array<string, int|string> $data */
    private function send(string $path, array $data): Response|JsonResponse
    {
        try {
            return $this->provider->post($path, $data)
                ?? $this->failure('Personalization is currently unavailable.', 503);
        } catch (ConnectionException) {
            return $this->failure('The personalization provider could not be reached.', 503);
        }
    }

    private function payload(Response $response): stdClass|JsonResponse
    {
        $payload = json_decode($response->body());

        if (! $response->redirect() && $payload instanceof stdClass) {
            if (($payload->success ?? null) === false && is_string($payload->message ?? null)) {
                return $payload;
            }

            if ($response->successful() && ($payload->success ?? null) === true && is_string($payload->status ?? null)) {
                return $payload;
            }
        }

        return $this->invalidResponse();
    }

    private function matchesTracking(stdClass $payload, string $digest): bool
    {
        return is_int($payload->id ?? null) && $payload->id > 0
            && is_string($payload->tracking_id ?? null) && $this->digest($payload->tracking_id) === $digest;
    }

    private function digest(string $trackingId): string
    {
        return hash('sha256', strtoupper($trackingId));
    }

    private function owner(string $digest): ?int
    {
        $owner = DB::table('personalization_tracking')->where('tracking_digest', $digest)->value('user_id');

        return $owner === null ? null : (int) $owner;
    }

    private function hasSubmission(int $providerId, string $digest): bool
    {
        return DB::table('personalization_submissions')->where('provider_id', $providerId)->where('tracking_digest', $digest)->exists();
    }

    private function notFound(): JsonResponse
    {
        return $this->failure('Personalization not found.', 404);
    }

    private function invalidResponse(): JsonResponse
    {
        return $this->failure('The personalization provider returned an invalid response.', 502);
    }

    private function failure(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
