<?php

namespace App\Console\Commands;

use App\Models\WhatsappContact;
use Illuminate\Console\Command;

class CleanWhatsappContactPhones extends Command
{
    protected $signature = 'whatsapp:clean-contact-phones';

    protected $description = 'Clean corrupted phone values (group JIDs, broadcast JIDs) from WhatsApp contacts';

    public function handle(): int
    {
        $contacts = WhatsappContact::all();
        $total = $contacts->count();
        $cleaned = 0;

        $this->info("Scanning {$total} WhatsApp contacts...");

        foreach ($contacts as $contact) {
            $phone = $contact->phone;

            if (blank($phone)) {
                continue;
            }

            if (! $this->isCorruptedPhone($phone)) {
                continue;
            }

            $msgCount = $contact->messages()->count();
            $this->warn("  Corrupted: ID={$contact->id} phone=\"{$phone}\" name=\"{$contact->name}\" messages={$msgCount}");

            $contact->messages()->update(['contact_id' => null]);
            $contact->delete();
            $cleaned++;
        }

        $this->newLine();

        if ($cleaned === 0) {
            $this->info('No corrupted phone values found.');
        } else {
            $this->info("Cleaned {$cleaned} corrupted phone value(s) out of {$total} contacts.");
        }

        return self::SUCCESS;
    }

    private function isCorruptedPhone(string $phone): bool
    {
        $lower = strtolower($phone);

        if (str_ends_with($lower, '@g.us')) {
            return true;
        }

        if (str_ends_with($lower, '@broadcast') || str_ends_with($lower, '@newsletter')) {
            return true;
        }

        if ($lower === 'status@broadcast') {
            return true;
        }

        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '120363')) {
            return true;
        }

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return true;
        }

        return false;
    }
}
