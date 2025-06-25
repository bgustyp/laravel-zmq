# Laravel ZMQ Broadcasting Driver

A ZeroMQ broadcast driver for Laravel 5-12 with support for PUB/SUB and DEALER/ROUTER patterns.

## Requirements

- PHP 8.1+
- Laravel 5.5 - 12.0
- ZeroMQ PHP Extension

## Installation

### Laravel 5.5 - 10.x

Add the package to your `composer.json`:

```bash
composer require bgustyp/laravel-zmq
```

The service provider will be auto-discovered.

### Laravel 11.x - 12.x

Add the package to your `composer.json`:

```bash
composer require bgustyp/laravel-zmq
```

The service provider will be auto-discovered. If auto-discovery doesn't work, manually register the service provider in `config/app.php`:

```php
'providers' => [
    // ...
    Bgustyp\LaravelZmq\ZmqServiceProvider::class,
],
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Bgustyp\LaravelZmq\ZmqServiceProvider"
```

This will create `config/zmq.php` with the following configuration:

```php
<?php

return [
    'default' => 'publish',

    'connections' => [
        'publish' => [
            'dsn'       => 'tcp://127.0.0.1:5555',
            'method'    => \ZMQ::SOCKET_PUB,
        ],

        'subscribe' => [
            'dsn'    => 'tcp://0.0.0.0:5555',
            'method'    => \ZMQ::SOCKET_SUB,
        ],

        'dealer' => [
            'dsn'       => 'tcp://127.0.0.1:5556',
            'method'    => \ZMQ::SOCKET_DEALER,
            'action'    => 'connect', // 'connect' or 'bind'
            'identity'  => null, // Set identity for dealer socket
            'linger'    => 0, // Socket linger value
        ],

        'router' => [
            'dsn'       => 'tcp://0.0.0.0:5556',
            'method'    => \ZMQ::SOCKET_ROUTER,
            'action'    => 'bind', // 'connect' or 'bind'
            'linger'    => 0, // Socket linger value
        ],
    ],
    
    'debug_logs' => true,
    
    'context' => [
        'io_threads' => 1,
        'max_sockets' => 1024,
    ],
];
```

## Broadcasting Configuration

Update your `config/broadcasting.php`:

```php
'default' => env('BROADCAST_DRIVER', 'zmq'),

'connections' => [
    'zmq' => [
        'driver' => 'zmq',
        'connection' => env('ZMQ_CONNECTION', 'publish'),
    ],
    
    // ... other connections
],
```

## Usage

### PUB/SUB Pattern (Traditional Broadcasting)

```php
// Broadcasting an event
event(new UserRegistered($user));

// Or using the broadcast facade
Broadcast::event('user.registered', ['user' => $user]);
```

### DEALER/ROUTER Pattern (Request-Reply)

```php
// Using DEALER socket for sending requests
$dealer = app('zmq.connection.dealer');
$socket = $dealer->connect();

// Send a request
$socket->send('Hello Server', \ZMQ::MODE_SNDMORE);
$socket->send(json_encode([
    'action' => 'process_data',
    'data' => ['key' => 'value']
]));

// Receive response
$response = $socket->recv();
```

```php
// Using ROUTER socket for handling requests
$router = app('zmq.connection.router');
$socket = $router->connect();

// Receive request
$identity = $socket->recv();
$message = $socket->recv();

// Send response
$socket->send($identity, \ZMQ::MODE_SNDMORE);
$socket->send('Response Data');
```

## Artisan Commands

Package ini menyediakan beberapa Artisan command untuk testing, monitoring, dan menjalankan server ZMQ.

### Available Commands

#### 1. `zmq:test` - Testing ZMQ Connections
```bash
# Test PUB/SUB publishing
php artisan zmq:test --connection=publish --message="Hello World"

# Test DEALER client
php artisan zmq:test --connection=dealer --message="Test request"

# Test DEALER server
php artisan zmq:test --connection=dealer --dealer-server --timeout=30
```

#### 2. `zmq:server` - Run ZMQ Server
```bash
# Jalankan DEALER server
php artisan zmq:server dealer

# Jalankan ROUTER server dengan timeout
php artisan zmq:server router --timeout=60

# Jalankan SUBSCRIBE server dengan verbose output
php artisan zmq:server subscribe --verbose
```

#### 3. `zmq:monitor` - Monitor ZMQ Connections
```bash
# Monitor semua koneksi
php artisan zmq:monitor

# Monitor dengan format JSON
php artisan zmq:monitor --format=json

# Watch mode (monitoring berkelanjutan)
php artisan zmq:monitor --watch
```

### Contoh Penggunaan Lengkap

#### Testing PUB/SUB Pattern
```bash
# Terminal 1: Jalankan SUBSCRIBE server
php artisan zmq:server subscribe --timeout=30

# Terminal 2: Test publishing
php artisan zmq:test --connection=publish --message="Hello from Laravel!"
```

#### Testing DEALER/ROUTER Pattern
```bash
# Terminal 1: Jalankan ROUTER server
php artisan zmq:server router --timeout=30

# Terminal 2: Test DEALER client
php artisan zmq:test --connection=dealer --message="Process this data"
```

Untuk dokumentasi lengkap tentang Artisan commands, lihat [docs/artisan-commands.md](docs/artisan-commands.md).

## Socket Types Supported

- **PUB/SUB**: Traditional broadcasting pattern
- **DEALER/ROUTER**: Request-reply pattern with load balancing
- **REQ/REP**: Simple request-reply pattern (can be added via configuration)

## Advanced Configuration

### Custom Socket Options

You can configure additional socket options in the config:

```php
'dealer' => [
    'dsn'       => 'tcp://127.0.0.1:5556',
    'method'    => \ZMQ::SOCKET_DEALER,
    'action'    => 'connect',
    'identity'  => 'worker-1', // Set unique identity
    'linger'    => 1000, // Wait 1 second before closing
],
```

### Multiple Connections

You can define multiple connections for different purposes:

```php
'connections' => [
    'broadcast' => [
        'dsn'       => 'tcp://127.0.0.1:5555',
        'method'    => \ZMQ::SOCKET_PUB,
    ],
    
    'worker1' => [
        'dsn'       => 'tcp://127.0.0.1:5556',
        'method'    => \ZMQ::SOCKET_DEALER,
        'action'    => 'connect',
        'identity'  => 'worker-1',
    ],
    
    'worker2' => [
        'dsn'       => 'tcp://127.0.0.1:5556',
        'method'    => \ZMQ::SOCKET_DEALER,
        'action'    => 'connect',
        'identity'  => 'worker-2',
    ],
],
```

## Environment Variables

Add these to your `.env` file:

```env
BROADCAST_DRIVER=zmq
ZMQ_CONNECTION=publish
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
