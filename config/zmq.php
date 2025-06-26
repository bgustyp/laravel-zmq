<?php

return [
    'default' => env('ZMQ_CONNECTION', 'publish'),

    'connections' => [
        'publish' => [
            'dsn' => env('ZMQ_PUB_DSN', 'tcp://127.0.0.1:5555'),
            'method' => \ZMQ::SOCKET_PUB,
            'action' => 'bind',
            'linger' => 1000,
            'hwm' => 1000,
            // libzmq 4.3.4+ options
            'connect_timeout' => env('ZMQ_CONNECT_TIMEOUT', 5000),
            'heartbeat' => [
                'interval' => env('ZMQ_HEARTBEAT_IVL', 30000), // 30 seconds
                'timeout' => env('ZMQ_HEARTBEAT_TIMEOUT', 10000), // 10 seconds
                'ttl' => env('ZMQ_HEARTBEAT_TTL', 60000), // 60 seconds
            ],
        ],

        'subscribe' => [
            'dsn' => env('ZMQ_SUB_DSN', 'tcp://127.0.0.1:5555'),
            'method' => \ZMQ::SOCKET_SUB,
            'action' => 'connect',
            'linger' => 0,
            'connect_timeout' => env('ZMQ_CONNECT_TIMEOUT', 5000),
        ],

        'dealer' => [
            'dsn' => env('ZMQ_DEALER_DSN', 'tcp://127.0.0.1:5556'),
            'method' => \ZMQ::SOCKET_DEALER,
            'action' => 'connect',
            'identity' => env('ZMQ_DEALER_IDENTITY'),
            'linger' => 1000,
            'connect_timeout' => env('ZMQ_CONNECT_TIMEOUT', 5000),
        ],

        'router' => [
            'dsn' => env('ZMQ_ROUTER_DSN', 'tcp://0.0.0.0:5556'),
            'method' => \ZMQ::SOCKET_ROUTER,
            'action' => 'bind',
            'linger' => 1000,
            'heartbeat' => [
                'interval' => env('ZMQ_HEARTBEAT_IVL', 30000),
                'timeout' => env('ZMQ_HEARTBEAT_TIMEOUT', 10000),
                'ttl' => env('ZMQ_HEARTBEAT_TTL', 60000),
            ],
        ],
    ],

    'debug_logs' => env('ZMQ_DEBUG_LOGS', false),

    'context' => [
        'io_threads' => env('ZMQ_IO_THREADS', 1),
        'max_sockets' => env('ZMQ_MAX_SOCKETS', 1024),
    ],

    // libzmq 4.3.4+ Security features
    'security' => [
        'curve' => [
            'enabled' => env('ZMQ_CURVE_ENABLED', false),
            'server_key' => env('ZMQ_CURVE_SERVER_KEY'),
            'public_key' => env('ZMQ_CURVE_PUBLIC_KEY'),
            'secret_key' => env('ZMQ_CURVE_SECRET_KEY'),
        ],
        'zap' => [
            'domain' => env('ZMQ_ZAP_DOMAIN', ''),
        ],
    ],

    // Compatibility settings
    'compatibility' => [
        'enforce_434' => env('ZMQ_ENFORCE_434', false),
        'auto_detect_features' => true,
        'fallback_on_missing_features' => true,
    ],
];
