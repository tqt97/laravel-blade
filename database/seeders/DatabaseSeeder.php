<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(BookableResourceSeeder::class);

        if (! app()->environment('local', 'testing') && (! filled(config('app.seed_admin_email')) || ! filled(config('app.seed_admin_password')))) {
            throw new RuntimeException('Refusing to seed the admin account outside local/testing without SEED_ADMIN_EMAIL and SEED_ADMIN_PASSWORD.');
        }

        $adminEmail = (string) (config('app.seed_admin_email') ?: 'admin@example.test');
        $adminPassword = (string) (config('app.seed_admin_password') ?: 'password');

        User::updateOrCreate(['email' => $adminEmail], [
            'name' => 'Administrator',
            'password' => Hash::make($adminPassword),
            'is_admin' => true,
        ]);

        $targetUsers = 10000;
        $existingUsers = User::query()->regularUsers()->count();
        $remainingUsers = max(0, $targetUsers - $existingUsers);

        if ($remainingUsers > 0) {
            User::factory()->count($remainingUsers)->create();
        }
    }
}
