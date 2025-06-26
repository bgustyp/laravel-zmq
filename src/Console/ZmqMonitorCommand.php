<?php

declare(strict_types=1);

namespace Bgustyp\LaravelZmq\Console;

use Illuminate\Console\Command;
use Bgustyp\LaravelZmq\ZmqManager;

class ZmqMonitorCommand extends Command
{
    protected $signature = 'zmq:monitor 
                           {--format=table : Output format (table, json)}';

    protected $description = 'Monitor ZMQ connections health';

    public function handle(ZmqManager $zmqManager): int
    {
        $format = $this->option('format');
        $healthData = $zmqManager->healthCheck();

        if ($format === 'json') {
            $this->line(json_encode($healthData, JSON_PRETTY_PRINT));
            return 0;
        }

        // Table format
        $headers = ['Connection', 'Status', 'Socket Type', 'DSN', 'Timestamp'];
        $rows = [];

        foreach ($healthData as $connection) {
            $status = $connection['status'] === 'healthy' ? '✓ Healthy' : '✗ Unhealthy';
            $error = $connection['error'] ?? '';

            $rows[] = [
                $connection['name'],
                $status . ($error ? " ({$error})" : ''),
                $connection['socket_type'] ?? 'Unknown',
                $connection['dsn'] ?? 'N/A',
                $connection['timestamp'] ?? 'N/A',
            ];
        }

        $this->table($headers, $rows);
        return 0;
    }
}
