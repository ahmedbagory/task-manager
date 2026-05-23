<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class WhatsappBridgeProcessService
{
    private const ALLOWED_COMMANDS = [
        'jlist', 'describe', 'status', 'restart', 'start', 'stop', 'save', 'logs', 'delete',
    ];

    private string $pm2Bin;

    private string $nodeBin;

    private string $pathEnv;

    private string $bridgePath;

    private string $pm2Name;

    private string $home;

    public function __construct()
    {
        $this->pm2Name = config('whatsapp_bridge.pm2_name', 'whatsapp-bridge');
        $this->bridgePath = config('whatsapp_bridge.bridge_path', base_path('whatsapp-bridge'));
        $this->nodeBin = config('whatsapp_bridge.node_bin', '/opt/alt/alt-nodejs20/root/usr/bin/node');
        $this->home = $_SERVER['HOME'] ?? '/home/' . get_current_user();
        $this->pm2Bin = config('whatsapp_bridge.pm2_bin', $this->home . '/.npm-global/bin/pm2');
        $this->pathEnv = config('whatsapp_bridge.path_env',
            $this->home . '/.npm-global/bin:/opt/alt/alt-nodejs20/root/usr/bin:/usr/local/bin:/usr/bin:/bin'
        );
    }

    public function pm2Exists(): bool
    {
        return file_exists($this->pm2Bin) && is_executable($this->pm2Bin);
    }

    public function getPm2Bin(): string
    {
        return $this->pm2Bin;
    }

    public function getNodeBin(): string
    {
        return $this->nodeBin;
    }

    /**
     * @return array{success: bool, output: string, error: string, exit_code: int}
     */
    public function getStatus(): array
    {
        return $this->runPm2(['describe', $this->pm2Name], config('whatsapp_bridge.status_timeout', 10));
    }

    /**
     * @return array{success: bool, output: string, error: string, exit_code: int}
     */
    public function getJlist(): array
    {
        return $this->runPm2(['jlist'], config('whatsapp_bridge.status_timeout', 10));
    }

    /**
     * @return array{success: bool, processes: array<int, array<string, mixed>>}
     */
    public function getParsedStatus(): array
    {
        $result = $this->getJlist();

        if (! $result['success']) {
            return ['success' => false, 'processes' => []];
        }

        $decoded = json_decode($result['output'], true);

        if (! is_array($decoded)) {
            return ['success' => false, 'processes' => []];
        }

        $matching = array_values(array_filter($decoded, fn (array $p) => ($p['name'] ?? '') === $this->pm2Name));

        return ['success' => true, 'processes' => $matching];
    }

    /**
     * @return array{success: bool, output: string, error: string, exit_code: int}
     */
    public function restart(): array
    {
        $parsed = $this->getParsedStatus();

        if ($parsed['success'] && count($parsed['processes']) > 0) {
            $status = $parsed['processes'][0]['pm2_env']['status'] ?? 'unknown';

            if (in_array($status, ['online', 'stopping', 'launching'], true)) {
                $result = $this->runPm2(
                    ['restart', $this->pm2Name, '--update-env'],
                    config('whatsapp_bridge.restart_timeout', 30)
                );
            } else {
                $result = $this->startFresh();
            }
        } else {
            $result = $this->startFresh();
        }

        if ($result['success']) {
            $this->runPm2(['save'], 10);
        }

        return $result;
    }

    /**
     * @return array{success: bool, output: string, error: string, exit_code: int}
     */
    public function startFresh(): array
    {
        $ecosystemFile = $this->bridgePath . '/ecosystem.config.cjs';

        if (file_exists($ecosystemFile)) {
            return $this->runPm2(
                ['start', $ecosystemFile],
                config('whatsapp_bridge.restart_timeout', 30)
            );
        }

        return $this->runPm2([
            'start', 'src/index.js',
            '--name', $this->pm2Name,
            '--time',
            '--restart-delay', '5000',
            '--max-memory-restart', '300M',
            '--interpreter', $this->nodeBin,
        ], config('whatsapp_bridge.restart_timeout', 30));
    }

    /**
     * @return array{success: bool, output: string, error: string, exit_code: int}
     */
    public function getLogs(int $lines = 0): array
    {
        $lines = $lines ?: (int) config('whatsapp_bridge.log_lines', 80);

        return $this->runPm2(
            ['logs', $this->pm2Name, '--lines', (string) $lines, '--nostream'],
            config('whatsapp_bridge.status_timeout', 10)
        );
    }

    public function getNodeVersion(): string
    {
        $process = new Process([$this->nodeBin, '--version']);
        $process->setEnv($this->buildEnv());
        $process->setTimeout(5);

        try {
            $process->run();

            return trim($process->getOutput()) ?: 'unknown';
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    public function authFolderExists(): bool
    {
        return is_dir($this->bridgePath . '/auth');
    }

    public function createRestartFlag(): bool
    {
        $dir = storage_path('app/whatsapp-bridge');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return (bool) file_put_contents($dir . '/restart.flag', (string) time());
    }

    public function hasRestartFlag(): bool
    {
        return file_exists(storage_path('app/whatsapp-bridge/restart.flag'));
    }

    public function removeRestartFlag(): bool
    {
        $path = storage_path('app/whatsapp-bridge/restart.flag');

        if (file_exists($path)) {
            return unlink($path);
        }

        return false;
    }

    /**
     * @return array{
     *   pm2_found: bool,
     *   pm2_bin: string,
     *   node_version: string,
     *   bridge_status: string,
     *   restart_count: int|null,
     *   uptime: string|null,
     *   memory: string|null,
     *   auth_exists: bool,
     *   pid: int|null,
     * }
     */
    public function getDiagnostics(): array
    {
        $diag = [
            'pm2_found' => $this->pm2Exists(),
            'pm2_bin' => $this->pm2Bin,
            'node_version' => $this->getNodeVersion(),
            'bridge_status' => 'unknown',
            'restart_count' => null,
            'uptime' => null,
            'memory' => null,
            'auth_exists' => $this->authFolderExists(),
            'pid' => null,
        ];

        $parsed = $this->getParsedStatus();

        if ($parsed['success'] && count($parsed['processes']) > 0) {
            $proc = $parsed['processes'][0];
            $env = $proc['pm2_env'] ?? [];
            $monit = $proc['monit'] ?? [];

            $diag['bridge_status'] = $env['status'] ?? 'unknown';
            $diag['restart_count'] = $env['restart_time'] ?? null;
            $diag['pid'] = $proc['pid'] ?? null;

            if (isset($env['pm_uptime']) && $env['pm_uptime'] > 0) {
                $uptimeSeconds = (int) ((microtime(true) * 1000 - $env['pm_uptime']) / 1000);
                $diag['uptime'] = $this->formatUptime($uptimeSeconds);
            }

            if (isset($monit['memory']) && $monit['memory'] > 0) {
                $diag['memory'] = round($monit['memory'] / 1024 / 1024, 1) . ' MB';
            }
        }

        return $diag;
    }

    /**
     * @param  list<string>  $pm2Args
     * @return array{success: bool, output: string, error: string, exit_code: int}
     */
    private function runPm2(array $pm2Args, int $timeout = 15): array
    {
        $subCommand = $pm2Args[0] ?? '';

        if (! in_array($subCommand, self::ALLOWED_COMMANDS, true)) {
            return [
                'success' => false,
                'output' => '',
                'error' => "PM2 sub-command not allowed: {$subCommand}",
                'exit_code' => -1,
            ];
        }

        if (! $this->pm2Exists()) {
            return [
                'success' => false,
                'output' => '',
                'error' => "PM2 binary not found at: {$this->pm2Bin}",
                'exit_code' => -1,
            ];
        }

        $command = array_merge([$this->pm2Bin], $pm2Args);
        $process = new Process($command);
        $process->setWorkingDirectory($this->bridgePath);
        $process->setEnv($this->buildEnv());
        $process->setTimeout($timeout);

        try {
            $process->run();
            $exitCode = $process->getExitCode();
            $stdout = $process->getOutput();
            $stderr = $process->getErrorOutput();

            Log::channel('single')->debug('PM2 command executed', [
                'command' => implode(' ', $pm2Args),
                'exit_code' => $exitCode,
            ]);

            return [
                'success' => $exitCode === 0,
                'output' => trim($stdout),
                'error' => trim($stderr),
                'exit_code' => $exitCode ?? -1,
            ];
        } catch (\Throwable $e) {
            Log::channel('single')->error('PM2 command failed', [
                'command' => implode(' ', $pm2Args),
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
                'exit_code' => -1,
            ];
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildEnv(): array
    {
        return [
            'HOME' => $this->home,
            'PATH' => $this->pathEnv,
            'PM2_HOME' => $this->home . '/.pm2',
            'NODE_ENV' => 'production',
        ];
    }

    private function formatUptime(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }

        if ($seconds < 3600) {
            return intdiv($seconds, 60) . 'm ' . ($seconds % 60) . 's';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours < 24) {
            return $hours . 'h ' . $minutes . 'm';
        }

        $days = intdiv($hours, 24);
        $remainingHours = $hours % 24;

        return $days . 'd ' . $remainingHours . 'h';
    }
}
