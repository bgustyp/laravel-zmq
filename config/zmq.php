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
        ],

        'subscribe' => [
            'dsn' => env('ZMQ_SUB_DSN', 'tcp://127.0.0.1:5555'),
            'method' => \ZMQ::SOCKET_SUB,
            'action' => 'connect',
            'linger' => 0,
        ],

        'dealer' => [
            'dsn' => env('ZMQ_DEALER_DSN', 'tcp://127.0.0.1:5556'),
            'method' => \ZMQ::SOCKET_DEALER,
            'action' => 'connect',
            'identity' => env('ZMQ_DEALER_IDENTITY'),
            'linger' => 1000,
        ],

        'router' => [
            'dsn' => env('ZMQ_ROUTER_DSN', 'tcp://0.0.0.0:5556'),
            'method' => \ZMQ::SOCKET_ROUTER,
            'action' => 'bind',
            'linger' => 1000,
        ],
    ],

    'debug_logs' => env('ZMQ_DEBUG_LOGS', false),

    'context' => [
        'io_threads' => env('ZMQ_IO_THREADS', 1),
        'max_sockets' => env('ZMQ_MAX_SOCKETS', 1024),
    ],
];
