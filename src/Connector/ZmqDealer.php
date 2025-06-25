<?php

namespace Bgustyp\LaravelZmq\Connector;

use Illuminate\Support\Facades\Config;

/**
 * Class ZmqDealer
 * @package Bgustyp\LaravelZmq\Connector
 */
class ZmqDealer extends ZmqConnector
{
    /**
     * ZmqDealer constructor.
     * @param string $connection
     */
    public function __construct($connection = 'dealer')
    {
        parent::__construct($connection);
    }

    /**
     * Connect to the socket for dealer pattern.
     * @return \ZMQSocket
     */
    public function connect()
    {
        $context = new \ZMQContext();
        $socket_method = \Config::get(sprintf('zmq.connections.%s.method', $this->connection), \ZMQ::SOCKET_DEALER);
        $socket = $context->getSocket($socket_method);

        // Check if we should bind or connect based on configuration
        $action = \Config::get(sprintf('zmq.connections.%s.action', $this->connection), 'connect');

        if ($action === 'bind') {
            $socket->bind($this->dsn());
        } else {
            $socket->connect($this->dsn());
        }

        // Set identity if configured
        $identity = \Config::get(sprintf('zmq.connections.%s.identity', $this->connection));
        if ($identity) {
            $socket->setSockOpt(\ZMQ::SOCKOPT_IDENTITY, $identity);
        }

        // Set linger if configured
        $linger = \Config::get(sprintf('zmq.connections.%s.linger', $this->connection), 0);
        $socket->setSockOpt(\ZMQ::SOCKOPT_LINGER, $linger);

        return $socket;
    }
}
