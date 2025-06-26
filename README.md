# Laravel ZMQ

A simple and improved ZeroMQ broadcast driver for Laravel with PHP 8.1+ compatibility and better error handling.

## Features

- ✅ **PHP 8.1+ Compatible** - Fixed nullable parameter deprecation warnings
- ✅ **Multiple Socket Types** - PUB/SUB, DEALER/ROUTER support
- ✅ **Laravel Broadcasting Integration** - Works with Laravel's event system
- ✅ **Health Monitoring** - Built-in health check commands
- ✅ **Proper Error Handling** - Comprehensive exception handling
- ✅ **Simple Configuration** - Easy to set up and use

## Installation

```bash
composer require bgustyp/laravel-zmq
```

The service provider will be auto-discovered. For manual registration:

```php
// config/app.php
'providers' => [
    Bgustyp\LaravelZmq\ZmqServiceProvider::class,
],
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=zmq-config
```

Update your `.env` file:

```env
# Broadcasting
BROADCAST_DRIVER=zmq
ZMQ_CONNECTION=publish

# Connection DSNs
ZMQ_PUB_DSN=tcp://127.0.0.1:5555
ZMQ_SUB_DSN=tcp://127.0.0.1:5555
ZMQ_DEALER_DSN=tcp://127.0.0.1:5556
ZMQ_ROUTER_DSN=tcp://0.0.0.0:5556

# Debugging
ZMQ_DEBUG_LOGS=true
```

Update your `config/broadcasting.php`:

```php
'connections' => [
    'zmq' => [
        'driver' => 'zmq',
        'connection' => env('ZMQ_CONNECTION', 'publish'),
    ],
    // ... other connections
],
```

## Usage

### Broadcasting Events

```php
// Using Laravel's event system
event(new UserRegistered($user));

// Using the broadcast facade
Broadcast::event('user.registered', ['user' => $user]);

// Using the ZMQ facade directly
use Bgustyp\LaravelZmq\Facades\ZmqManager as ZMQ;

ZMQ::publish('user.channel', ['message' => 'Hello World']);
```

### Direct Socket Communication

```php
use Bgustyp\LaravelZmq\Facades\ZmqManager as ZMQ;

// Publisher
$publisher = ZMQ::connection('publish');
$publisher->publish('news.sports', ['headline' => 'Team wins!']);

// Subscriber
$subscriber = ZMQ::connection('subscribe');
$subscriber->subscribe(['news.sports']);
$message = $subscriber->receive(5000); // 5 second timeout

// DEALER/ROUTER
$dealer = ZMQ::connection('dealer');
$dealer->send(json_encode(['action' => 'process', 'data' => $data]));
$response = $dealer->receive(5000);
```

## Console Commands

### Testing Connections

```bash
# Test publisher
php artisan zmq:test --connection=publish --message="Hello World" --channel=test

# Test dealer
php artisan zmq:test --connection=dealer --message="Test request"

# Send multiple messages
php artisan zmq:test --connection=publish --count=10
```

### Running Servers

```bash
# Subscribe server (listens for 30 seconds)
php artisan zmq:server subscribe --timeout=30

# Router server (processes requests)
php artisan zmq:server router --timeout=60
```

### Health Monitoring

```bash
# Check health of all connections
php artisan zmq:monitor

# JSON output
php artisan zmq:monitor --format=json
```

## Configuration Options

The `config/zmq.php` file supports these options:

```php
return [
    'default' => 'publish',

    'connections' => [
        'publish' => [
            'dsn' => 'tcp://127.0.0.1:5555',
            'method' => \ZMQ::SOCKET_PUB,
            'action' => 'bind',          // 'bind' or 'connect'
            'linger' => 1000,            // Socket linger time
            'hwm' => 1000,               // High water mark
        ],
        // ... more connections
    ],

    'debug_logs' => true,

    'context' => [
        'io_threads' => 1,
        'max_sockets' => 1024,
    ],
];
```