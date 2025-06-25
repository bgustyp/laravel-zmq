<?php

namespace Bgustyp\LaravelZmq\Connector;

use Illuminate\Support\Facades\Config;

/**
 * Class ZmqRouter
 * @package Bgustyp\LaravelZmq\Connector
 */
class ZmqRouter extends ZmqConnector
{
    /**
     * ZmqRouter constructor.
     * @param string $connection
     */
    public function __construct($connection = 'router')
    {
        parent::__construct($connection);
    }

    /**
     * Connect to the socket for router pattern.
     * @return \ZMQSocket
     */
    public function connect()
    {
        $context = new \ZMQContext();
        $socket_method = Config::get(sprintf('zmq.connections.%s.method', $this->connection), \ZMQ::SOCKET_ROUTER);
        $socket = $context->getSocket($socket_method);

        // Check if we should bind or connect based on configuration
        $action = Config::get(sprintf('zmq.connections.%s.action', $this->connection), 'bind');

        if ($action === 'bind') {
            $socket->bind($this->dsn());
        } else {
            $socket->connect($this->dsn());
        }

        // Set linger if configured
        $linger = Config::get(sprintf('zmq.connections.%s.linger', $this->connection), 0);
        $socket->setSockOpt(\ZMQ::SOCKOPT_LINGER, $linger);

        return $socket;
    }
}
