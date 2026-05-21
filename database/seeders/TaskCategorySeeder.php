<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\TaskCategory;
use Illuminate\Database\Seeder;

class TaskCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'department_code' => 'MNT',
                'name' => 'Electrical Issue',
                'code' => 'MNT-ELEC',
                'description' => 'Electrical faults, power outages, and wiring issues.',
                'is_active' => true,
            ],
            [
                'department_code' => 'MNT',
                'name' => 'Plumbing Issue',
                'code' => 'MNT-PLMB',
                'description' => 'Leaks, blocked drains, and water pressure problems.',
                'is_active' => true,
            ],
            [
                'department_code' => 'IT',
                'name' => 'Hardware Support',
                'code' => 'IT-HW',
                'description' => 'Computers, printers, and device failures.',
                'is_active' => true,
            ],
            [
                'department_code' => 'IT',
                'name' => 'Software Support',
                'code' => 'IT-SW',
                'description' => 'Application errors, access issues, and software installation.',
                'is_active' => true,
            ],
            [
                'department_code' => 'SEC',
                'name' => 'Incident Report',
                'code' => 'SEC-INC',
                'description' => 'Security incidents and suspicious activity reports.',
                'is_active' => true,
            ],
            [
                'department_code' => null,
                'name' => 'General Inquiry',
                'code' => 'GEN-INQ',
                'description' => 'Uncategorized requests pending dispatcher triage.',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $categoryData) {
            $departmentId = null;

            if (! is_null($categoryData['department_code'])) {
                $departmentId = Department::query()
                    ->where('code', $categoryData['department_code'])
                    ->value('id');
            }

            TaskCategory::query()->updateOrCreate(
                ['code' => $categoryData['code']],
                [
                    'department_id' => $departmentId,
                    'name' => $categoryData['name'],
                    'description' => $categoryData['description'],
                    'is_active' => $categoryData['is_active'],
                ],
            );
        }
    }
}
