<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('admin.email');
        $password = config('admin.password');

        if (Validator::make(['email' => $email], ['email' => 'required|string|email|max:255'])->fails()
            || ! is_string($password) || strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new RuntimeException('Set a valid ADMIN_EMAIL and an ADMIN_PASSWORD of 12 to 72 bytes in .env.');
        }

        $admin = User::firstOrNew(['email' => Str::lower($email)]);

        if ($admin->exists && ! $admin->is_admin) {
            throw new RuntimeException('ADMIN_EMAIL already belongs to a regular user.');
        }

        if (! $admin->exists) {
            $admin->name = config('app.name').' Admin';
            $admin->is_admin = true;
            $admin->role = 'super_admin';
        }

        if (! $admin->exists || ! Hash::check($password, $admin->password)) {
            $admin->password = $password;
            $admin->save();
        }
    }
}
