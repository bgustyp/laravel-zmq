<?php

declare(strict_types=1);

namespace Bgustyp\LaravelZmq\Console;

use Illuminate\Console\Command;
use Bgustyp\LaravelZmq\Support\LibZmqCompatibility;

class ZmqVersionCommand extends Command
{
    protected $signature = 'zmq:version 
                           {--format=table : Output format (table, json)}
                           {--check-compatibility : Check libzmq 4.3.4 compatibility}';

    protected $description = 'Show ZMQ version information and compatibility';

    public function handle(): int
    {
        $format = $this->option('format');
        $checkCompatibility = $this->option('check-compatibility');

        $versionInfo = LibZmqCompatibility::getVersionInfo();
        $capabilities = LibZmqCompatibility::getCapabilities();

        if ($format === 'json') {
            $this->line(json_encode([
                'version_info' => $versionInfo,
                'capabilities' => $capabilities,
                'libzmq_434_compatible' => LibZmqCompatibility::isLibZmq434OrHigher(),
            ], JSON_PRETTY_PRINT));
            return 0;
        }

        // Table format
        $this->info('ZMQ Version Information');
        $this->line('');

        $this->table(['Component', 'Version'], [
            ['PHP ZMQ Extension', $versionInfo['php_zmq_version']],
            ['LibZMQ Library', $versionInfo['libzmq_version'] ?? 'Unknown'],
            ['LibZMQ 4.3.4+ Compatible', LibZmqCompatibility::isLibZmq434OrHigher() ? '✓ Yes' : '✗ No'],
        ]);

        $this->line('');
        $this->info('Available Features');

        $featureRows = [];
        foreach ($capabilities as $feature => $available) {
            $featureRows[] = [
                str_replace('_', ' ', ucfirst($feature)),
                $available ? '✓ Available' : '✗ Not Available'
            ];
        }

        $this->table(['Feature', 'Status'], $featureRows);

        if ($checkCompatibility) {
            $this->line('');
            $this->checkCompatibility();
        }

        return 0;
    }

    private function checkCompatibility(): void
    {
        $this->info('Compatibility Check');
        $this->line('');

        $config = config('zmq', []);
        $validation = LibZmqCompatibility::validateConfiguration($config);

        if ($validation['valid']) {
            $this->line('<fg=green>✓ Configuration is compatible with libzmq 4.3.4</>');
        } else {
            $this->line('<fg=red>✗ Configuration has compatibility issues:</>');
            foreach ($validation['errors'] as $error) {
                $this->line("  - {$error}");
            }
        }

        if (!empty($validation['warnings'])) {
            $this->line('');
            $this->line('<fg=yellow>Warnings:</>');
            foreach ($validation['warnings'] as $warning) {
                $this->line("  - {$warning}");
            }
        }

        // Check dependencies
        $this->line('');
        $this->info('Dependencies');
        $this->checkDependencies();
    }

    private function checkDependencies(): void
    {
        $dependencies = [
            'ZMQ Extension' => extension_loaded('zmq'),
            'Sodium Extension' => extension_loaded('sodium'),
        ];

        foreach ($dependencies as $name => $available) {
            $status = $available ? '<fg=green>✓ Available</>' : '<fg=red>✗ Missing</>';
            $this->line("  {$name}: {$status}");
        }
    }
}
