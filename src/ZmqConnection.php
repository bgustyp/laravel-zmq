<?php

declare(strict_types=1);

namespace Bgustyp\LaravelZmq;

use ZMQ;
use ZMQContext;
use ZMQSocket;
use Bgustyp\LaravelZmq\Exceptions\ZmqException;

class ZmqConnection
{
    private array $config;
    private ?ZMQSocket $socket = null;
    private ZMQContext $context;

    public function __construct(array $config, ZMQContext $context)
    {
        $this->config = $config;
        $this->context = $context;
    }

    public function publish(string $channel, mixed $data): bool
    {
        try {
            $socket = $this->getSocket();
            $message = is_string($data) ? $data : json_encode($data);

            if ($this->config['method'] === ZMQ::SOCKET_PUB) {
                $socket->send($channel, ZMQ::MODE_SNDMORE);
                $socket->send($message);
            } else {
                $socket->send($message);
            }

            return true;
        } catch (\Exception $e) {
            throw new ZmqException("Failed to publish message: " . $e->getMessage(), 0, $e);
        }
    }

    public function send(string $message): bool
    {
        try {
            $this->getSocket()->send($message);
            return true;
        } catch (\Exception $e) {
            throw new ZmqException("Failed to send message: " . $e->getMessage(), 0, $e);
        }
    }

    public function receive(?int $timeout = null): ?string
    {
        try {
            $socket = $this->getSocket();

            if ($timeout !== null) {
                $socket->setSockOpt(ZMQ::SOCKOPT_RCVTIMEO, $timeout);
            }

            $result = $socket->recv();
            return $result === false ? null : $result;
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Resource temporarily unavailable')) {
                return null; // Timeout
            }
            throw new ZmqException("Failed to receive message: " . $e->getMessage(), 0, $e);
        }
    }

    public function subscribe(array $channels): void
    {
        if ($this->config['method'] !== ZMQ::SOCKET_SUB) {
            throw new ZmqException("Subscribe only available for SUB sockets");
        }

        $socket = $this->getSocket();

        foreach ($channels as $channel) {
            $socket->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, $channel);
        }
    }

    public function getSocket(): ZMQSocket
    {
        if ($this->socket === null) {
            $this->createSocket();
        }

        return $this->socket;
    }

    public function getSocketType(): int
    {
        return $this->config['method'];
    }

    public function getDsn(): string
    {
        return $this->config['dsn'];
    }

    public function close(): void
    {
        if ($this->socket) {
            try {
                $this->socket->setSockOpt(ZMQ::SOCKOPT_LINGER, 0);
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
            $this->socket = null;
        }
    }

    private function createSocket(): void
    {
        try {
            $this->socket = new ZMQSocket($this->context, $this->config['method']);

            $this->setSocketOptions();

            $action = $this->config['action'] ?? $this->getDefaultAction();

            if ($action === 'bind') {
                $this->socket->bind($this->config['dsn']);
            } else {
                $this->socket->connect($this->config['dsn']);
            }
        } catch (\Exception $e) {
            throw new ZmqException("Failed to create socket: " . $e->getMessage(), 0, $e);
        }
    }

    private function setSocketOptions(): void
    {
        // Set linger
        if (isset($this->config['linger'])) {
            $this->socket->setSockOpt(ZMQ::SOCKOPT_LINGER, $this->config['linger']);
        }

        // Set identity for DEALER sockets
        if (isset($this->config['identity']) && $this->config['method'] === ZMQ::SOCKET_DEALER) {
            $this->socket->setSockOpt(ZMQ::SOCKOPT_IDENTITY, $this->config['identity']);
        }

        // Set high water mark
        if (isset($this->config['hwm'])) {
            $this->socket->setSockOpt(ZMQ::SOCKOPT_SNDHWM, $this->config['hwm']);
            $this->socket->setSockOpt(ZMQ::SOCKOPT_RCVHWM, $this->config['hwm']);
        }
    }

    private function getDefaultAction(): string
    {
        $bindSockets = [ZMQ::SOCKET_PUB, ZMQ::SOCKET_ROUTER, ZMQ::SOCKET_PULL, ZMQ::SOCKET_REP];
        return in_array($this->config['method'], $bindSockets) ? 'bind' : 'connect';
    }
}
