<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            EmpresaSeeder::class,
            LocalSeeder::class,
            RolSeeder::class,
            ModuloSeeder::class,
            PermisoSeeder::class,
            UserSeeder::class,
        ]);
    }
}
