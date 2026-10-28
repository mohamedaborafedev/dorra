<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Sample users may only be seeded locally or in tests.');
        }
        foreach (UserRole::cases() as $role) {
            $email = $role->value.'@dorra.test';
            // Never promote an existing account or replace its password.
            if (User::where('email', $email)->exists()) {
                continue;
            }
            $user = new User;
            $user->name = ucfirst($role->value);
            $user->email = $email;
            $user->role = $role;
            $user->password = 'password';
            $user->email_verified_at = now();
            $user->save();
        }
    }
}
