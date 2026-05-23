<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WhatsappBridgeProcessService;
use Illuminate\Console\Command;

class WhatsappBridgeResetSession extends Command
{
    protected $signature = 'whatsapp:bridge-reset-session
                            {--force : Skip confirmation prompt}';

    protected $description = 'Reset the WhatsApp bridge session (stops bridge, backs up & removes auth, restarts)';

    public function handle(WhatsappBridgeProcessService $service): int
    {
        if (! $service->pm2Exists()) {
            $this->error('PM2 not found at: ' . $service->getPm2Bin());

            return self::FAILURE;
        }

        $bridgePath = config('whatsapp_bridge.bridge_path', base_path('whatsapp-bridge'));
        $authDir = $bridgePath . '/auth';

        if (! $this->option('force')) {
            if (! $this->confirm('This will delete the WhatsApp session. You will need to scan a new QR code. Continue?')) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }
        }

        $this->info('Stopping bridge...');
        $service->getStatus();

        if (is_dir($authDir)) {
            $backupName = 'auth_backup_' . date('Ymd_His');
            $backupPath = $bridgePath . '/' . $backupName;

            $this->info("Backing up auth to {$backupName}...");

            if (rename($authDir, $backupPath)) {
                $this->info('Auth backed up successfully.');
            } else {
                $this->error('Failed to back up auth directory.');

                return self::FAILURE;
            }
        } else {
            $this->warn('No auth directory found. Nothing to reset.');
        }

        $this->info('Restarting bridge (will generate new QR)...');
        $result = $service->restart();

        if ($result['success']) {
            $this->info('Bridge restarted. A new QR code will be generated.');
            $this->info('Check the WhatsApp Session page in the admin panel to scan it.');
        } else {
            $this->error('Bridge restart failed: ' . $result['error']);
        }

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }
}
