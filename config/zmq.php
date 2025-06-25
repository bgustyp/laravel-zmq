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

    'debug_logs' => true, // enable this to log all published messages to debug channel

    // Additional ZMQ context options
    'context' => [
        'io_threads' => 1, // Number of I/O threads
        'max_sockets' => 1024, // Maximum number of sockets
    ],
];
