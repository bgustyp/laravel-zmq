<?php

declare(strict_types=1);

namespace Bgustyp\LaravelZmq\Console;

use Illuminate\Console\Command;
use Bgustyp\LaravelZmq\ZmqManager;
use ZMQ;

class ZmqServerCommand extends Command
{
    protected $signature = 'zmq:server 
                           {connection : Connection name to use}
                           {--timeout=0 : Timeout in seconds (0 = infinite)}';

    protected $description = 'Run a ZMQ server';

    private bool $shouldStop = false;

    public function handle(ZmqManager $zmqManager): int
    {
        $connectionName = $this->argument('connection');
        $timeout = (int) $this->option('timeout');

        $this->info("Starting ZMQ server for connection: {$connectionName}");

        try {
            $connection = $zmqManager->connection($connectionName);
            $socket = $connection->getSocket();
            $socketType = $connection->getSocketType();

            if ($socketType === ZMQ::SOCKET_SUB) {
                $connection->subscribe(['']); // Subscribe to all
            }

            $endTime = $timeout > 0 ? time() + $timeout : PHP_INT_MAX;
            $messageCount = 0;

            while (!$this->shouldStop && time() < $endTime) {
                try {
                    if ($socketType === ZMQ::SOCKET_SUB) {
                        $envelope = $socket->recv(ZMQ::MODE_NOBLOCK);
                        if ($envelope !== false) {
                            $message = $socket->recv();
                            $messageCount++;
                            $this->line("[{$messageCount}] Channel: {$envelope}, Message: {$message}");
                        }
                    } else {
                        $message = $socket->recv(ZMQ::MODE_NOBLOCK);
                        if ($message !== false) {
                            $messageCount++;
                            $this->line("[{$messageCount}] Received: {$message}");
                        }
                    }
                } catch (\Exception $e) {
                    // No message available
                }

                usleep(100000); // 100ms
            }

            $this->info("Server stopped. Processed {$messageCount} messages.");
            return 0;
        } catch (\Exception $e) {
            $this->error("Server failed: " . $e->getMessage());
            return 1;
        }
    }
}
