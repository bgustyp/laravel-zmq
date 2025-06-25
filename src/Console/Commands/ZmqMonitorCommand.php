<?php

namespace Bgustyp\LaravelZmq\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class ZmqMonitorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zmq:monitor 
                            {--connection= : Specific connection to monitor}
                            {--format=table : Output format (table, json, csv)}
                            {--watch : Watch mode (continuous monitoring)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor ZMQ connections and status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connection = $this->option('connection');
        $format = $this->option('format');
        $watch = $this->option('watch');

        if ($watch) {
            $this->runWatchMode($connection, $format);
        } else {
            $this->showStatus($connection, $format);
        }

        return 0;
    }

    /**
     * Show ZMQ status
     */
    protected function showStatus($connection = null, $format = 'table')
    {
        $this->info("ZMQ Connection Status");
        $this->line("=====================");

        $connections = Config::get('zmq.connections', []);

        if ($connection) {
            if (!isset($connections[$connection])) {
                $this->error("Connection '{$connection}' not found in configuration");
                return;
            }
            $connections = [$connection => $connections[$connection]];
        }

        $data = [];
        foreach ($connections as $name => $config) {
            $data[] = [
                'Connection' => $name,
                'DSN' => $config['dsn'] ?? 'N/A',
                'Method' => $this->getSocketMethodName($config['method'] ?? 'N/A'),
                'Action' => $config['action'] ?? 'N/A',
                'Identity' => $config['identity'] ?? 'N/A',
                'Linger' => $config['linger'] ?? 'N/A',
                'Status' => $this->testConnection($name, $config) ? '✓ Active' : '✗ Inactive'
            ];
        }

        switch ($format) {
            case 'json':
                $this->output->write(json_encode($data, JSON_PRETTY_PRINT));
                break;
            case 'csv':
                $this->outputCsv($data);
                break;
            default:
                $this->table(['Connection', 'DSN', 'Method', 'Action', 'Identity', 'Linger', 'Status'], $data);
        }
    }

    /**
     * Run watch mode
     */
    protected function runWatchMode($connection = null, $format = 'table')
    {
        $this->info("Starting ZMQ monitor in watch mode...");
        $this->info("Press Ctrl+C to stop");

        $iteration = 0;

        while (true) {
            $iteration++;
            $this->info("\n" . str_repeat("=", 50));
            $this->info("Iteration: {$iteration} | Time: " . now()->format('Y-m-d H:i:s'));
            $this->info(str_repeat("=", 50));

            $this->showStatus($connection, $format);

            sleep(5); // Update every 5 seconds
        }
    }

    /**
     * Test connection
     */
    protected function testConnection($name, $config)
    {
        try {
            $dsn = $config['dsn'] ?? '';
            $method = $config['method'] ?? \ZMQ::SOCKET_PUB;

            // Parse DSN
            $parsed = parse_url($dsn);
            if (!$parsed) {
                return false;
            }

            $host = $parsed['host'] ?? '127.0.0.1';
            $port = $parsed['port'] ?? 5555;

            // Try to connect
            $context = new \ZMQContext();
            $socket = $context->getSocket($method);

            // Set timeout
            $socket->setSockOpt(\ZMQ::SOCKOPT_RCVTIMEO, 1000);
            $socket->setSockOpt(\ZMQ::SOCKOPT_SNDTIMEO, 1000);

            if (isset($config['action']) && $config['action'] === 'bind') {
                $socket->bind($dsn);
            } else {
                $socket->connect($dsn);
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get socket method name
     */
    protected function getSocketMethodName($method)
    {
        $methods = [
            \ZMQ::SOCKET_PUB => 'PUB',
            \ZMQ::SOCKET_SUB => 'SUB',
            \ZMQ::SOCKET_DEALER => 'DEALER',
            \ZMQ::SOCKET_ROUTER => 'ROUTER',
            \ZMQ::SOCKET_REQ => 'REQ',
            \ZMQ::SOCKET_REP => 'REP',
            \ZMQ::SOCKET_PUSH => 'PUSH',
            \ZMQ::SOCKET_PULL => 'PULL',
        ];

        return $methods[$method] ?? 'UNKNOWN';
    }

    /**
     * Output CSV format
     */
    protected function outputCsv($data)
    {
        if (empty($data)) {
            return;
        }

        // Output headers
        $headers = array_keys($data[0]);
        $this->output->write(implode(',', $headers) . "\n");

        // Output data
        foreach ($data as $row) {
            $this->output->write(implode(',', array_values($row)) . "\n");
        }
    }
}
