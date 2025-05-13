<?php

namespace Database\Seeders;

use App\Models\Users\Users\User;
use Database\Seeders\Bombinhas\BombinhasSeeder;
use Illuminate\Database\Seeder;


class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(BombinhasSeeder::class);
    }
}
