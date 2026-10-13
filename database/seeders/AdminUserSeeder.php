<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed (or update) the single admin user from ADMIN_EMAIL/ADMIN_PASSWORD.
     * Idempotent — safe to re-run.
     */
    public function run(): void
    {
        $email = config('magpie.admin_email');
        $password = config('magpie.admin_password');

        if (! $email || ! $password) {
            $this->command->warn('ADMIN_EMAIL/ADMIN_PASSWORD not set — skipping admin user seed.');

            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            ['name' => 'Magpie Admin', 'password' => $password],
        );
    }
}
