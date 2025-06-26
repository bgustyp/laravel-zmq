<?php

namespace Bgustyp\LaravelZmq\Console\Commands;

use Illuminate\Console\Command;
use Bgustyp\LaravelZmq\Connector\ZmqDealer;
use Bgustyp\LaravelZmq\Connector\ZmqRouter;
use Bgustyp\LaravelZmq\Connector\ZmqSubscribe;

class ZmqServerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zmq:server 
                            {type : Server type (dealer, router, subscribe)}
                            {--connection= : Connection name from config}
                            {--timeout=0 : Timeout in seconds (0 = infinite)}
                            {--verbose : Verbose output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run ZMQ server (DEALER, ROUTER, or SUBSCRIBE)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->argument('type');
        $connection = $this->option('connection') ?: $type;
        $timeout = (int) $this->option('timeout');
        $verbose = $this->option('verbose');

        $this->info("Starting ZMQ {$type} server...");
        $this->info("Connection: {$connection}");
        $this->info("Timeout: " . ($timeout > 0 ? "{$timeout}s" : "infinite"));

        try {
            switch ($type) {
                case 'dealer':
                    $this->runDealerServer($connection, $timeout, $verbose);
                    break;
                case 'router':
                    $this->runRouterServer($connection, $timeout, $verbose);
                    break;
                case 'subscribe':
                    $this->runSubscribeServer($connection, $timeout, $verbose);
                    break;
                default:
                    $this->error("Unknown server type: {$type}");
                    $this->info("Available types: dealer, router, subscribe");
                    return 1;
            }
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Run DEALER server
     */
    protected function runDealerServer($connection, $timeout, $verbose)
    {
        $this->info("Starting DEALER server...");

        $dealer = new ZmqDealer($connection);
        $socket = $dealer->connect();

        $startTime = time();
        $requestCount = 0;
        $running = true;

        $this->info("✓ DEALER server started, waiting for requests...");

        while ($running) {
            try {
                $request = $socket->recv(\ZMQ::MODE_NOBLOCK);
                if ($request !== false) {
                    $requestCount++;

                    if ($verbose) {
                        $this->info("✓ Request {$requestCount} received: {$request}");
                    } else {
                        $this->info("✓ Request {$requestCount} received");
                    }

                    // Process request
                    $response = $this->processRequest($request, 'dealer');

                    $socket->send($response);

                    if ($verbose) {
                        $this->info("✓ Response sent: {$response}");
                    }
                }
            } catch (\ZMQException $e) {
                // No request available, continue
            }

            // Check timeout
            if ($timeout > 0 && (time() - $startTime) >= $timeout) {
                $this->info("Timeout reached, stopping server...");
                $running = false;
            }

            usleep(100000); // 100ms
        }

        $this->info("✓ DEALER server stopped. Processed {$requestCount} requests.");
    }

    /**
     * Run ROUTER server
     */
    protected function runRouterServer($connection, $timeout, $verbose)
    {
        $this->info("Starting ROUTER server...");

        $router = new ZmqRouter($connection);
        $socket = $router->connect();

        $startTime = time();
        $requestCount = 0;
        $running = true;

        $this->info("✓ ROUTER server started, waiting for requests...");

        while ($running) {
            try {
                $identity = $socket->recv(\ZMQ::MODE_NOBLOCK);
                if ($identity !== false) {
                    $request = $socket->recv(\ZMQ::MODE_NOBLOCK);
                    if ($request !== false) {
                        $requestCount++;

                        if ($verbose) {
                            $this->info("✓ Request {$requestCount} from {$identity}: {$request}");
                        } else {
                            $this->info("✓ Request {$requestCount} from {$identity}");
                        }

                        // Process request
                        $response = $this->processRequest($request, 'router');

                        $socket->send($identity, \ZMQ::MODE_SNDMORE);
                        $socket->send($response);

                        if ($verbose) {
                            $this->info("✓ Response sent to {$identity}: {$response}");
                        }
                    }
                }
            } catch (\ZMQException $e) {
                // No request available, continue
            }

            // Check timeout
            if ($timeout > 0 && (time() - $startTime) >= $timeout) {
                $this->info("Timeout reached, stopping server...");
                $running = false;
            }

            usleep(100000); // 100ms
        }

        $this->info("✓ ROUTER server stopped. Processed {$requestCount} requests.");
    }

    /**
     * Run SUBSCRIBE server
     */
    protected function runSubscribeServer($connection, $timeout, $verbose)
    {
        $this->info("Starting SUBSCRIBE server...");

        $subscribe = new ZmqSubscribe($connection);
        $socket = $subscribe->connect();

        // Subscribe to all messages
        $socket->setSockOpt(\ZMQ::SOCKOPT_SUBSCRIBE, '');

        $startTime = time();
        $messageCount = 0;
        $running = true;

        $this->info("✓ SUBSCRIBE server started, waiting for messages...");

        while ($running) {
            try {
                $topic = $socket->recv(\ZMQ::MODE_NOBLOCK);
                if ($topic !== false) {
                    $message = $socket->recv(\ZMQ::MODE_NOBLOCK);
                    if ($message !== false) {
                        $messageCount++;

                        if ($verbose) {
                            $this->info("✓ Message {$messageCount}:");
                            $this->line("  Topic: {$topic}");
                            $this->line("  Message: {$message}");
                        } else {
                            $this->info("✓ Message {$messageCount} received on topic: {$topic}");
                        }

                        // Process message
                        $this->processMessage($topic, $message);
                    }
                }
            } catch (\ZMQException $e) {
                // No message available, continue
            }

            // Check timeout
            if ($timeout > 0 && (time() - $startTime) >= $timeout) {
                $this->info("Timeout reached, stopping server...");
                $running = false;
            }

            usleep(100000); // 100ms
        }

        $this->info("✓ SUBSCRIBE server stopped. Received {$messageCount} messages.");
    }

    /**
     * Process incoming request
     */
    protected function processRequest($request, $type)
    {
        $data = json_decode($request, true);

        if (!$data) {
            return json_encode([
                'status' => 'error',
                'message' => 'Invalid JSON',
                'timestamp' => now()->toISOString()
            ]);
        }

        // Simple request processing
        $action = $data['action'] ?? 'unknown';
        $message = $data['message'] ?? '';

        switch ($action) {
            case 'ping':
                return json_encode([
                    'status' => 'success',
                    'action' => 'pong',
                    'message' => 'Server is alive',
                    'timestamp' => now()->toISOString()
                ]);

            case 'echo':
                return json_encode([
                    'status' => 'success',
                    'action' => 'echo',
                    'message' => $message,
                    'timestamp' => now()->toISOString()
                ]);

            case 'process':
                return json_encode([
                    'status' => 'success',
                    'action' => 'processed',
                    'message' => "Processed: {$message}",
                    'timestamp' => now()->toISOString()
                ]);

            default:
                return json_encode([
                    'status' => 'success',
                    'action' => $action,
                    'message' => "Received: {$message}",
                    'timestamp' => now()->toISOString()
                ]);
        }
    }

    /**
     * Process incoming message
     */
    protected function processMessage($topic, $message)
    {
        $data = json_decode($message, true);

        if (!$data) {
            $this->warn("Invalid JSON message received on topic: {$topic}");
            return;
        }

        // Log message processing
        $this->info("Processing message on topic: {$topic}");

        // You can add custom message processing logic here
        // For example, storing to database, triggering events, etc.
    }
}
