<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class ResetDepartmentsStructureSeeder extends Seeder
{
    public function run(): void
    {
        $beforeCount = Department::count();

        $structure = [
            [
                'name' => 'الإدارة',
                'code' => '01-ADMIN',
                'description' => 'الإدارة الرئيسية للشركة',
                'children' => [
                    ['name' => 'الإدارة الرئيسية', 'code' => '01-ADMIN-HQ'],
                ],
            ],
            [
                'name' => 'الصيدليات',
                'code' => '02-PHARMACIES',
                'description' => 'شبكة الصيدليات',
                'children' => [
                    ['name' => 'صيدلية الدواء الكبرى 1', 'code' => '02-PHARMACIES-01'],
                    ['name' => 'صيدلية الدواء الكبرى 2', 'code' => '02-PHARMACIES-02'],
                    ['name' => 'صيدلية الدواء الكبرى 3', 'code' => '02-PHARMACIES-03'],
                    ['name' => 'صيدلية الدواء الكبرى 4', 'code' => '02-PHARMACIES-04'],
                    ['name' => 'صيدلية الدواء الكبرى 5', 'code' => '02-PHARMACIES-05'],
                    ['name' => 'صيدلية الدواء الكبرى 7', 'code' => '02-PHARMACIES-07'],
                    ['name' => 'صيدلية الدواء الكبرى 8', 'code' => '02-PHARMACIES-08'],
                    ['name' => 'صيدلية الدواء الكبرى 9', 'code' => '02-PHARMACIES-09'],
                    ['name' => 'صيدلية الدواء الكبرى 10', 'code' => '02-PHARMACIES-10'],
                    ['name' => 'صيدلية الدواء الكبرى 11', 'code' => '02-PHARMACIES-11'],
                    ['name' => 'صيدلية الدواء الكبرى 12', 'code' => '02-PHARMACIES-12'],
                    ['name' => 'صيدلية الدواء الكبرى 16', 'code' => '02-PHARMACIES-16'],
                    ['name' => 'صيدلية الدواء الكبرى 17', 'code' => '02-PHARMACIES-17'],
                    ['name' => 'صيدلية الدواء الكبرى 18', 'code' => '02-PHARMACIES-18'],
                    ['name' => 'صيدلية الدواء الكبرى 20', 'code' => '02-PHARMACIES-20'],
                    ['name' => 'صيدلية الدواء الكبرى 21', 'code' => '02-PHARMACIES-21'],
                    ['name' => 'صيدلية الدواء الكبرى 22', 'code' => '02-PHARMACIES-22'],
                    ['name' => 'صيدلية الدواء الكبرى 25', 'code' => '02-PHARMACIES-25'],
                    ['name' => 'صيدلية العناية الكبرى 1', 'code' => '02-PHARMACIES-CARE-01'],
                ],
            ],
            [
                'name' => 'المستودعات',
                'code' => '03-WAREHOUSES',
                'description' => 'المستودعات والوحدات التخزينية',
                'children' => [
                    ['name' => 'مستودع الأدوية', 'code' => '03-WAREHOUSES-MEDICINES'],
                    ['name' => 'مستودع التجميل', 'code' => '03-WAREHOUSES-COSMETICS'],
                    ['name' => 'مستودع حليب', 'code' => '03-WAREHOUSES-MILK'],
                    ['name' => 'مستودع توالف', 'code' => '03-WAREHOUSES-DAMAGED'],
                ],
            ],
            [
                'name' => 'العمليات',
                'code' => '04-OPERATIONS',
                'description' => 'عمليات التشغيل والخدمات المساندة',
                'children' => [
                    ['name' => 'الصيانة', 'code' => '04-OPS-MNT'],
                    ['name' => 'التوصيل', 'code' => '04-OPS-DLV'],
                    ['name' => 'العمال', 'code' => '04-OPS-WRK'],
                ],
            ],
        ];

        foreach ($structure as $departmentData) {
            $children = $departmentData['children'];
            unset($departmentData['children']);

            $parent = Department::query()->updateOrCreate(
                ['code' => $departmentData['code']],
                [
                    'name' => $departmentData['name'],
                    'description' => $departmentData['description'],
                    'is_active' => true,
                    'parent_id' => null,
                ],
            );

            foreach ($children as $childData) {
                Department::query()->updateOrCreate(
                    ['code' => $childData['code']],
                    [
                        'name' => $childData['name'],
                        'description' => $departmentData['name'],
                        'is_active' => true,
                        'parent_id' => $parent->id,
                    ],
                );
            }
        }

        $afterCount = Department::count();

        $this->command?->info("Departments before: {$beforeCount}");
        $this->command?->info("Departments after: {$afterCount}");
    }
}
