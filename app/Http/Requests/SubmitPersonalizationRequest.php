<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPersonalizationRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'tracking_id' => ['required', 'string', 'regex:/\A[A-Za-z0-9]{15}\z/'],
            'type' => ['required', 'string', 'in:info'],
        ];
    }
}
