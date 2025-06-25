<?php

namespace Bgustyp\LaravelZmq\Connector;

use Illuminate\Support\Facades\Config;

/**
 * Class ZmqSubscribe
 * @package Bgustyp\LaravelZmq\Connector
 */
class ZmqSubscribe extends ZmqConnector
{
    /**
     * ZmqSubscribe constructor.
     * @param string $connection
     */
    public function __construct($connection = 'subscribe')
    {
        parent::__construct($connection);
    }

    /**
     * Connect to the socket for subscribing.
     *
     * @return \ZMQSocket
     */
    public function connect()
    {
        $context = new \ZMQContext();
        $socket_method = Config::get(sprintf('zmq.connections.%s.method', $this->connection), \ZMQ::SOCKET_SUB);
        $socket  = $context->getSocket($socket_method);
        $socket->bind($this->dsn());

        return $socket;
    }
}
