<?php

namespace Bgustyp\LaravelZmq\Connector;

use Illuminate\Support\Facades\Config;

/**
 * Class ZmqConnector
 * @package Bgustyp\LaravelZmq\Connector
 */
abstract class ZmqConnector
{
    protected $connection;

    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return \ZMQSocket
     */
    abstract public function connect();

    protected function dsn()
    {
        return Config::get(sprintf('zmq.connections.%s.dsn', $this->connection), 'tcp://127.0.0.1:5555');
    }
}
