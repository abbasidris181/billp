<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyIdentityRequest extends FormRequest
{
    public function apiVersion(): string
    {
        return $this->route()->defaults['verification_version'] ?? 'v1';
    }

    public function service(): string
    {
        return $this->route()->defaults['verification_service'] ?? $this->validated('type');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $type = $this->route()->defaults['verification_type'];

        return [
            'type' => ['required', 'string', Rule::in([$type])],
            'idNumber' => ['required', 'string', in_array($type, ['phone', 'bvn_with_phone'], true) ? 'regex:/\A0[0-9]{10}\z/' : 'regex:/\A[0-9]{11}\z/'],
            'consent' => ['required', 'boolean:strict', 'accepted'],
        ];
    }
}
