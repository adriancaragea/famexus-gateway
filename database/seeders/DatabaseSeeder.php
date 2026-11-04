<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Family;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * A demo family to log in with locally; every password is "password".
     */
    public function run(): void
    {
        $family = Family::factory()->create(['name' => 'Demo family']);

        User::factory()->inFamily(Role::Owner, $family)->create(['name' => 'Demo Owner', 'email' => 'owner@famexus.local']);
        User::factory()->inFamily(Role::Adult, $family)->create(['name' => 'Demo Adult', 'email' => 'adult@famexus.local']);
        User::factory()->inFamily(Role::Child, $family)->create(['name' => 'Demo Child', 'email' => 'child@famexus.local']);
    }
}
