<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Satu akun superadmin. Kredensial dari .env (ADMIN_EMAIL, ADMIN_PASSWORD). */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('jmc.admin.email');
        $password = config('jmc.admin.password');

        if (blank($password)) {
            $password = Str::password(16, symbols: false);
            $this->command?->warn("ADMIN_PASSWORD kosong di .env. Password acak dibuat untuk {$email}: {$password}");
        }

        User::updateOrCreate(['email' => $email], [
            'name' => config('jmc.admin.name'),
            'password' => Hash::make($password),
            'role' => Role::Superadmin,
            'cabor_id' => null,
        ]);
    }
}
