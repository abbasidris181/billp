<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckPersonalizationRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'id' => ['required_without:tracking_id', 'missing_with:tracking_id', 'integer:strict', 'min:1'],
            'tracking_id' => ['required_without:id', 'missing_with:id', 'string', 'regex:/\A[A-Za-z0-9]{15}\z/'],
        ];
    }
}
