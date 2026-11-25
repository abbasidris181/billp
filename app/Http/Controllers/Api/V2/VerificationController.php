<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Clients\VerificationProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyIdentityRequest;
use Illuminate\Http\JsonResponse;

class VerificationController extends Controller
{
    public function __invoke(VerifyIdentityRequest $request, VerificationProvider $provider): JsonResponse
    {
        return $provider->verify($request, '/api/v1/verify');
    }
}
