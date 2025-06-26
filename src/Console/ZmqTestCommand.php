<?php

declare(strict_types=1);

namespace Bgustyp\LaravelZmq\Console;

use Illuminate\Console\Command;
use Bgustyp\LaravelZmq\ZmqManager;
use ZMQ;

class ZmqTestCommand extends Command
{
    protected $signature = 'zmq:test 
                           {--connection=publish : Connection to use}
                           {--message=Hello World : Message to send}
                           {--channel=test : Channel for PUB/SUB}
                           {--count=1 : Number of messages to send}';

    protected $description = 'Test ZMQ connections';

    public function handle(ZmqManager $zmqManager): int
    {
        $connection = $this->option('connection');
        $message = $this->option('message');
        $channel = $this->option('channel');
        $count = (int) $this->option('count');

        $this->info("Testing ZMQ connection: {$connection}");

        try {
            $conn = $zmqManager->connection($connection);
            $socketType = $conn->getSocketType();

            for ($i = 1; $i <= $count; $i++) {
                $payload = [
                    'message' => $message,
                    'sequence' => $i,
                    'timestamp' => now()->toISOString(),
                ];

                if ($socketType === ZMQ::SOCKET_PUB) {
                    $success = $conn->publish($channel, $payload);
                } else {
                    $success = $conn->send(json_encode($payload));
                }

                if ($success) {
                    $this->line("✓ Sent message {$i}/{$count}");
                } else {
                    $this->error("✗ Failed to send message {$i}");
                    return 1;
                }
            }

            $this->info("All messages sent successfully");
            return 0;
        } catch (\Exception $e) {
            $this->error("Test failed: " . $e->getMessage());
            return 1;
        }
    }
}
