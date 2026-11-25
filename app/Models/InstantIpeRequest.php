<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstantIpeRequest extends Model
{
    public const UNRESOLVED = ['queued', 'submitting', 'pending', 'needs_confirmation'];

    public const CHECKABLE = ['pending', 'needs_confirmation'];

    public const LABELS = [
        'queued' => 'Queued',
        'submitting' => 'Submitting',
        'pending' => 'Pending',
        'successful' => 'Successful',
        'failed' => 'Failed',
        'needs_confirmation' => 'Needs confirmation',
    ];

    protected function casts(): array
    {
        return [
            'nin' => 'encrypted',
            'next_check_at' => 'datetime',
            'last_manual_check_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }
}
