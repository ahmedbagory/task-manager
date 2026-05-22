<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\WhatsappContact;
use Illuminate\Database\Seeder;

class WhatsappContactRoutingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $department = Department::query()
            ->where('is_active', true)
            ->where('code', '04-OPS-MNT')
            ->first();

        $contact = WhatsappContact::query()->firstOrNew([
            'phone' => '201555960069',
        ]);

        $contact->name = $contact->name ?: 'Ahmed Elbagoury';
        $contact->department_id = $contact->department_id ?: $department?->id;
        $contact->default_location = 'ميجا 6';
        $contact->save();
    }
}
