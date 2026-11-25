<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Clients\VerificationProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyIdentityRequest;
use Illuminate\Http\JsonResponse;

class PhoneController extends Controller
{
    public function __invoke(VerifyIdentityRequest $request, VerificationProvider $provider): JsonResponse
    {
        return $provider->verify($request, '/api/v1/verify');
    }
}
