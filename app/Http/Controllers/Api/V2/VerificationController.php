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
        $path = $request->service() === 'bvn' ? '/api/v2/verify' : '/api/v1/verify';

        return $provider->verify($request, $path);
    }
}
