<?php

/**
 * Contoh penggunaan Laravel ZMQ untuk Laravel 11 dan 12
 * 
 * File ini menunjukkan cara menggunakan package ZMQ dengan Laravel 11 dan 12
 */

// 1. PUB/SUB Pattern - Broadcasting Events
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;

// Broadcasting event menggunakan facade
Broadcast::event('user.registered', [
    'user_id' => 123,
    'email' => 'user@example.com',
    'timestamp' => now()
]);

// Broadcasting event menggunakan Event class
Event::dispatch(new UserRegistered($user));

// 2. DEALER/ROUTER Pattern - Request-Reply
use Bgustyp\LaravelZmq\Connector\ZmqDealer;
use Bgustyp\LaravelZmq\Connector\ZmqRouter;

// Client menggunakan DEALER socket
$dealer = app('zmq.connection.dealer');
$socket = $dealer->connect();

// Kirim request
$socket->send('Hello Server', \ZMQ::MODE_SNDMORE);
$socket->send(json_encode([
    'action' => 'process_data',
    'data' => ['key' => 'value']
]));

// Terima response
$response = $socket->recv();
echo "Response: " . $response . "\n";

// Server menggunakan ROUTER socket
$router = app('zmq.connection.router');
$socket = $router->connect();

// Loop untuk menerima requests
while (true) {
    // Terima identity dan message
    $identity = $socket->recv();
    $message = $socket->recv();

    // Process message
    $data = json_decode($message, true);

    // Kirim response
    $socket->send($identity, \ZMQ::MODE_SNDMORE);
    $socket->send(json_encode([
        'status' => 'success',
        'result' => 'Processed: ' . $data['action']
    ]));
}

// 3. Multiple DEALER connections untuk load balancing
$worker1 = new ZmqDealer('worker1');
$worker2 = new ZmqDealer('worker2');

$socket1 = $worker1->connect();
$socket2 = $worker2->connect();

// 4. Custom configuration untuk DEALER
// Di config/zmq.php:
/*
'worker1' => [
    'dsn'       => 'tcp://127.0.0.1:5556',
    'method'    => \ZMQ::SOCKET_DEALER,
    'action'    => 'connect',
    'identity'  => 'worker-1',
    'linger'    => 1000,
],

'worker2' => [
    'dsn'       => 'tcp://127.0.0.1:5556',
    'method'    => \ZMQ::SOCKET_DEALER,
    'action'    => 'connect',
    'identity'  => 'worker-2',
    'linger'    => 1000,
],
*/

// 5. Broadcasting dengan custom connection
Broadcast::connection('zmq')->event('custom.channel', [
    'message' => 'Hello from custom connection'
]);

// 6. Artisan command untuk testing
// php artisan make:command TestZmqBroadcasting

class TestZmqBroadcasting extends Command
{
    protected $signature = 'zmq:test';
    protected $description = 'Test ZMQ broadcasting';

    public function handle()
    {
        $this->info('Testing ZMQ broadcasting...');

        // Test PUB/SUB
        Broadcast::event('test.event', ['message' => 'Hello ZMQ!']);
        $this->info('Event broadcasted successfully');

        // Test DEALER/ROUTER
        $dealer = app('zmq.connection.dealer');
        $socket = $dealer->connect();

        $socket->send('test', \ZMQ::MODE_SNDMORE);
        $socket->send('Hello Server');

        $this->info('DEALER message sent successfully');
    }
}

// 7. Queue worker dengan ZMQ
// Di config/queue.php:
/*
'connections' => [
    'zmq' => [
        'driver' => 'zmq',
        'connection' => 'dealer',
    ],
],
*/

// 8. Event listener untuk ZMQ events
class ZmqEventListener
{
    public function handle($event)
    {
        // Handle ZMQ event
        Log::info('ZMQ Event received', $event);
    }
}

// 9. Middleware untuk ZMQ authentication
class ZmqAuthMiddleware
{
    public function handle($request, Closure $next)
    {
        // Validate ZMQ connection
        if (!$this->validateZmqConnection($request)) {
            return response('Unauthorized', 401);
        }

        return $next($request);
    }

    private function validateZmqConnection($request)
    {
        // Implement your validation logic
        return true;
    }
}

// 10. Service Provider untuk custom ZMQ services
class CustomZmqServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('zmq.custom', function ($app) {
            return new CustomZmqService();
        });
    }
}

class CustomZmqService
{
    public function sendMessage($channel, $message)
    {
        $dealer = app('zmq.connection.dealer');
        $socket = $dealer->connect();

        $socket->send($channel, \ZMQ::MODE_SNDMORE);
        $socket->send(json_encode($message));

        return $socket->recv();
    }
}
