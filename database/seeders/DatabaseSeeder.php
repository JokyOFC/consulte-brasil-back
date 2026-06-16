<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Admin de desenvolvimento.
        $user = User::updateOrCreate(
            ['email' => 'admin@consultebrasil.test'],
            [
                'name' => 'Admin Consulte Brasil',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ],
        );

        $user->forceFill(['role' => 'admin'])->save();

        $this->call([
            PlanSeeder::class,
            QueryTypeSeeder::class,
            ProviderSeeder::class,
            CpfCnpjCatalogSeeder::class,
            ApiBrasilCatalogSeeder::class,
        ]);
    }
}
