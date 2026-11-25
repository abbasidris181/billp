<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token', 'api_key', 'api_key_selector'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = ['user' => 'User', 'admin' => 'Admin', 'super_admin' => 'Super admin'];

    public const STATUSES = ['active' => 'Active', 'suspended' => 'Suspended', 'archived' => 'Archived'];

    public static function apiKeySelector(string $key): string
    {
        return substr($key, 0, 26);
    }

    public function isActive(): bool
    {
        return ($this->account_status ?? 'active') === 'active';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'api_key' => 'encrypted',
            'password' => 'hashed',
        ];
    }
}
