<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WhatsappBridgeProcessService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WhatsappBridgeWatchdog extends Command
{
    protected $signature = 'whatsapp:bridge-watchdog';

    protected $description = 'Watchdog: check WhatsApp bridge health and restart if needed';

    public function handle(WhatsappBridgeProcessService $service): int
    {
        $logger = Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/whatsapp-watchdog.log'),
            'level' => 'debug',
        ]);

        if (! $service->pm2Exists()) {
            $msg = 'Watchdog: PM2 not found at ' . $service->getPm2Bin();
            $logger->error($msg);
            $this->error($msg);

            return self::FAILURE;
        }

        if ($service->hasRestartFlag()) {
            if (! $this->respectsCooldown($logger)) {
                return self::SUCCESS;
            }

            $logger->info('Watchdog: restart flag detected, restarting bridge');
            $this->info('Restart flag detected. Restarting...');

            $result = $service->restart();
            $service->removeRestartFlag();
            $this->writeCooldownTimestamp();

            if ($result['success']) {
                $logger->info('Watchdog: bridge restarted via flag');
                $this->info('Bridge restarted via flag.');
            } else {
                $logger->error('Watchdog: flag restart failed', ['error' => $result['error']]);
                $this->error('Flag restart failed: ' . $result['error']);
            }

            return $result['success'] ? self::SUCCESS : self::FAILURE;
        }

        $parsed = $service->getParsedStatus();

        if (! $parsed['success'] || count($parsed['processes']) === 0) {
            if (! $this->respectsCooldown($logger)) {
                return self::SUCCESS;
            }

            $logger->warning('Watchdog: bridge process not found in PM2, starting');
            $this->warn('Bridge not found in PM2. Starting...');

            $result = $service->restart();
            $this->writeCooldownTimestamp();

            if ($result['success']) {
                $logger->info('Watchdog: bridge started successfully');
                $this->info('Bridge started.');
            } else {
                $logger->error('Watchdog: start failed', ['error' => $result['error']]);
                $this->error('Start failed: ' . $result['error']);
            }

            return $result['success'] ? self::SUCCESS : self::FAILURE;
        }

        $proc = $parsed['processes'][0];
        $status = $proc['pm2_env']['status'] ?? 'unknown';

        if (in_array($status, ['stopped', 'errored'], true)) {
            if (! $this->respectsCooldown($logger)) {
                return self::SUCCESS;
            }

            $logger->warning("Watchdog: bridge status is '{$status}', restarting");
            $this->warn("Bridge status: {$status}. Restarting...");

            $result = $service->restart();
            $this->writeCooldownTimestamp();

            if ($result['success']) {
                $logger->info('Watchdog: bridge restarted from ' . $status);
                $this->info('Bridge restarted.');
            } else {
                $logger->error('Watchdog: restart failed', ['error' => $result['error']]);
                $this->error('Restart failed: ' . $result['error']);
            }

            return $result['success'] ? self::SUCCESS : self::FAILURE;
        }

        $logger->debug("Watchdog: bridge is {$status}, no action needed");
        $this->info("Bridge is {$status}. No action needed.");

        return self::SUCCESS;
    }

    private function respectsCooldown(\Psr\Log\LoggerInterface $logger): bool
    {
        $cooldownFile = storage_path('app/whatsapp-bridge/last-restart.timestamp');

        if (! file_exists($cooldownFile)) {
            return true;
        }

        $lastRestart = (int) file_get_contents($cooldownFile);
        $cooldown = (int) config('whatsapp_bridge.restart_cooldown_seconds', 30);

        if (time() - $lastRestart < $cooldown) {
            $remaining = $cooldown - (time() - $lastRestart);
            $logger->info("Watchdog: cooldown active, {$remaining}s remaining");
            $this->info("Cooldown active ({$remaining}s remaining). Skipping restart.");

            return false;
        }

        return true;
    }

    private function writeCooldownTimestamp(): void
    {
        $dir = storage_path('app/whatsapp-bridge');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($dir . '/last-restart.timestamp', (string) time());
    }
}
