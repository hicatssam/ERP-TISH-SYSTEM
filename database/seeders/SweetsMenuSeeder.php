<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Compatibility name for old local setup instructions.
 * This installation now seeds burger restaurant data only.
 */
class SweetsMenuSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(BurgerRestaurantDemoSeeder::class);
    }
}
