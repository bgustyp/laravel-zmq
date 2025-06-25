<?php

namespace Bgustyp\LaravelZmq\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Broadcast;
use Bgustyp\LaravelZmq\Connector\ZmqDealer;
use Bgustyp\LaravelZmq\Connector\ZmqRouter;
use Bgustyp\LaravelZmq\Connector\ZmqPublish;
use Bgustyp\LaravelZmq\Connector\ZmqSubscribe;

class ZmqTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zmq:test 
                            {--connection= : Connection to test (publish, subscribe, dealer, router)}
                            {--message= : Message to send}
                            {--dealer-server : Run as DEALER server}
                            {--router-server : Run as ROUTER server}
                            {--timeout=5 : Timeout in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test ZMQ connections and broadcasting';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connection = $this->option('connection') ?: 'publish';
        $message = $this->option('message') ?: 'Hello ZMQ from Laravel!';
        $timeout = (int) $this->option('timeout');

        $this->info("Testing ZMQ connection: {$connection}");
        $this->info("Message: {$message}");
        $this->info("Timeout: {$timeout} seconds");

        try {
            switch ($connection) {
                case 'publish':
                    $this->testPublish($message);
                    break;
                case 'subscribe':
                    $this->testSubscribe($timeout);
                    break;
                case 'dealer':
                    if ($this->option('dealer-server')) {
                        $this->runDealerServer($timeout);
                    } else {
                        $this->testDealer($message, $timeout);
                    }
                    break;
                case 'router':
                    if ($this->option('router-server')) {
                        $this->runRouterServer($timeout);
                    } else {
                        $this->testRouter($message, $timeout);
                    }
                    break;
                default:
                    $this->error("Unknown connection type: {$connection}");
                    return 1;
            }
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Test PUB/SUB publishing
     */
    protected function testPublish($message)
    {
        $this->info("Testing PUB/SUB publishing...");

        // Test broadcasting
        Broadcast::event('zmq.test', [
            'message' => $message,
            'timestamp' => now()->toISOString(),
            'connection' => 'publish'
        ]);

        $this->info("✓ Event broadcasted successfully");

        // Test direct socket
        $publish = new ZmqPublish();
        $socket = $publish->connect();

        $socket->send('zmq.test', \ZMQ::MODE_SNDMORE);
        $socket->send(json_encode([
            'message' => $message,
            'timestamp' => now()->toISOString(),
            'connection' => 'publish'
        ]));

        $this->info("✓ Message sent via socket successfully");
    }

    /**
     * Test PUB/SUB subscribing
     */
    protected function testSubscribe($timeout)
    {
        $this->info("Testing PUB/SUB subscribing (timeout: {$timeout}s)...");

        $subscribe = new ZmqSubscribe();
        $socket = $subscribe->connect();

        // Subscribe to all messages
        $socket->setSockOpt(\ZMQ::SOCKOPT_SUBSCRIBE, '');

        $startTime = time();
        $messageCount = 0;

        while ((time() - $startTime) < $timeout) {
            try {
                $topic = $socket->recv(\ZMQ::MODE_NOBLOCK);
                if ($topic !== false) {
                    $message = $socket->recv(\ZMQ::MODE_NOBLOCK);
                    if ($message !== false) {
                        $messageCount++;
                        $this->info("✓ Received message {$messageCount}:");
                        $this->line("  Topic: {$topic}");
                        $this->line("  Message: {$message}");
                    }
                }
            } catch (\ZMQException $e) {
                // No message available, continue
            }

            usleep(100000); // 100ms
        }

        if ($messageCount === 0) {
            $this->warn("No messages received within {$timeout} seconds");
        } else {
            $this->info("✓ Received {$messageCount} messages");
        }
    }

    /**
     * Test DEALER client
     */
    protected function testDealer($message, $timeout)
    {
        $this->info("Testing DEALER client...");

        $dealer = new ZmqDealer();
        $socket = $dealer->connect();

        // Send request
        $socket->send('test', \ZMQ::MODE_SNDMORE);
        $socket->send(json_encode([
            'action' => 'test',
            'message' => $message,
            'timestamp' => now()->toISOString()
        ]));

        $this->info("✓ Request sent, waiting for response...");

        // Wait for response
        $startTime = time();
        while ((time() - $startTime) < $timeout) {
            try {
                $response = $socket->recv(\ZMQ::MODE_NOBLOCK);
                if ($response !== false) {
                    $this->info("✓ Response received: {$response}");
                    return;
                }
            } catch (\ZMQException $e) {
                // No response available, continue
            }

            usleep(100000); // 100ms
        }

        $this->warn("No response received within {$timeout} seconds");
    }

    /**
     * Run DEALER server
     */
    protected function runDealerServer($timeout)
    {
        $this->info("Running DEALER server (timeout: {$timeout}s)...");

        $dealer = new ZmqDealer();
        $socket = $dealer->connect();

        $startTime = time();
        $requestCount = 0;

        while ((time() - $startTime) < $timeout) {
            try {
                $request = $socket->recv(\ZMQ::MODE_NOBLOCK);
                if ($request !== false) {
                    $requestCount++;
                    $this->info("✓ Request {$requestCount} received: {$request}");

                    // Send response
                    $response = json_encode([
                        'status' => 'success',
                        'request' => $request,
                        'timestamp' => now()->toISOString()
                    ]);

                    $socket->send($response);
                    $this->info("✓ Response sent: {$response}");
                }
            } catch (\ZMQException $e) {
                // No request available, continue
            }

            usleep(100000); // 100ms
        }

        if ($requestCount === 0) {
            $this->warn("No requests received within {$timeout} seconds");
        } else {
            $this->info("✓ Processed {$requestCount} requests");
        }
    }

    /**
     * Test ROUTER client
     */
    protected function testRouter($message, $timeout)
    {
        $this->info("Testing ROUTER client...");

        $router = new ZmqRouter();
        $socket = $router->connect();

        // Send request
        $socket->send('client-1', \ZMQ::MODE_SNDMORE);
        $socket->send(json_encode([
            'action' => 'test',
            'message' => $message,
            'timestamp' => now()->toISOString()
        ]));

        $this->info("✓ Request sent, waiting for response...");

        // Wait for response
        $startTime = time();
        while ((time() - $startTime) < $timeout) {
            try {
                $identity = $socket->recv(\ZMQ::MODE_NOBLOCK);
                if ($identity !== false) {
                    $response = $socket->recv(\ZMQ::MODE_NOBLOCK);
                    if ($response !== false) {
                        $this->info("✓ Response from {$identity}: {$response}");
                        return;
                    }
                }
            } catch (\ZMQException $e) {
                // No response available, continue
            }

            usleep(100000); // 100ms
        }

        $this->warn("No response received within {$timeout} seconds");
    }

    /**
     * Run ROUTER server
     */
    protected function runRouterServer($timeout)
    {
        $this->info("Running ROUTER server (timeout: {$timeout}s)...");

        $router = new ZmqRouter();
        $socket = $router->connect();

        $startTime = time();
        $requestCount = 0;

        while ((time() - $startTime) < $timeout) {
            try {
                $identity = $socket->recv(\ZMQ::MODE_NOBLOCK);
                if ($identity !== false) {
                    $request = $socket->recv(\ZMQ::MODE_NOBLOCK);
                    if ($request !== false) {
                        $requestCount++;
                        $this->info("✓ Request {$requestCount} from {$identity}: {$request}");

                        // Send response
                        $response = json_encode([
                            'status' => 'success',
                            'request' => $request,
                            'timestamp' => now()->toISOString()
                        ]);

                        $socket->send($identity, \ZMQ::MODE_SNDMORE);
                        $socket->send($response);
                        $this->info("✓ Response sent to {$identity}: {$response}");
                    }
                }
            } catch (\ZMQException $e) {
                // No request available, continue
            }

            usleep(100000); // 100ms
        }

        if ($requestCount === 0) {
            $this->warn("No requests received within {$timeout} seconds");
        } else {
            $this->info("✓ Processed {$requestCount} requests");
        }
    }
}
