<?php

namespace Database\Seeders;

use App\Services\Authorization\RbacInitializationService;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(RbacInitializationService::class)->seed();
    }
}
