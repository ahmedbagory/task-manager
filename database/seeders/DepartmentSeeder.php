<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            [
                'name' => 'Operations',
                'code' => 'OPS',
                'description' => 'General operations and field execution.',
                'is_active' => true,
            ],
            [
                'name' => 'Maintenance',
                'code' => 'MNT',
                'description' => 'Facilities and maintenance issues.',
                'is_active' => true,
            ],
            [
                'name' => 'Information Technology',
                'code' => 'IT',
                'description' => 'IT support and infrastructure tasks.',
                'is_active' => true,
            ],
            [
                'name' => 'Human Resources',
                'code' => 'HR',
                'description' => 'HR requests and employee-related issues.',
                'is_active' => true,
            ],
            [
                'name' => 'Security',
                'code' => 'SEC',
                'description' => 'Safety, incidents, and security operations.',
                'is_active' => true,
            ],
        ];

        foreach ($departments as $departmentData) {
            Department::query()->updateOrCreate(
                ['code' => $departmentData['code']],
                $departmentData,
            );
        }
    }
}
