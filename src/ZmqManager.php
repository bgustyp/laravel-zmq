<?php

declare(strict_types=1);

namespace Bgustyp\LaravelZmq;

use ZMQ;
use ZMQContext;
use ZMQSocket;
use InvalidArgumentException;
use Bgustyp\LaravelZmq\Exceptions\ZmqException;

class ZmqManager
{
    private array $connections = [];
    private ?ZMQContext $context = null;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->validateConfig();
        $this->initializeContext();
    }

    public function connection(?string $name = null): ZmqConnection
    {
        $name = $name ?: $this->getDefaultConnection();

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->makeConnection($name);
        }

        return $this->connections[$name];
    }

    public function publish(string $channel, mixed $data, ?string $connection = null): bool
    {
        try {
            return $this->connection($connection)->publish($channel, $data);
        } catch (\Exception $e) {
            $this->logError("Failed to publish to channel {$channel}", $e);
            return false;
        }
    }

    public function send(string $message, ?string $connection = null): bool
    {
        try {
            return $this->connection($connection)->send($message);
        } catch (\Exception $e) {
            $this->logError("Failed to send message", $e);
            return false;
        }
    }

    public function receive(?string $connection = null, ?int $timeout = null): ?string
    {
        try {
            return $this->connection($connection)->receive($timeout);
        } catch (\Exception $e) {
            $this->logError("Failed to receive message", $e);
            return null;
        }
    }

    public function healthCheck(): array
    {
        $results = [];

        foreach (array_keys($this->config['connections']) as $name) {
            $results[$name] = $this->checkConnection($name);
        }

        return $results;
    }

    public function closeAll(): void
    {
        foreach ($this->connections as $connection) {
            $connection->close();
        }

        $this->connections = [];
        $this->context = null;
    }

    private function validateConfig(): void
    {
        if (empty($this->config['connections'])) {
            throw new ZmqException('No ZMQ connections configured');
        }

        if (!extension_loaded('zmq')) {
            throw new ZmqException('ZMQ extension is not loaded');
        }
    }

    private function initializeContext(): void
    {
        $contextConfig = $this->config['context'] ?? [];

        $this->context = new ZMQContext(
            $contextConfig['io_threads'] ?? 1,
            true // persistent
        );
    }

    private function makeConnection(string $name): ZmqConnection
    {
        $config = $this->getConnectionConfig($name);
        return new ZmqConnection($config, $this->context);
    }

    private function getConnectionConfig(string $name): array
    {
        if (!isset($this->config['connections'][$name])) {
            throw new InvalidArgumentException("Connection '{$name}' not configured");
        }

        return array_merge([
            'debug_logs' => $this->config['debug_logs'] ?? false,
        ], $this->config['connections'][$name]);
    }

    private function checkConnection(string $name): array
    {
        try {
            $connection = $this->connection($name);

            return [
                'name' => $name,
                'status' => 'healthy',
                'socket_type' => $this->getSocketTypeName($connection->getSocketType()),
                'dsn' => $connection->getDsn(),
                'timestamp' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            return [
                'name' => $name,
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ];
        }
    }

    private function getSocketTypeName(int $type): string
    {
        return match ($type) {
            ZMQ::SOCKET_PUB => 'PUB',
            ZMQ::SOCKET_SUB => 'SUB',
            ZMQ::SOCKET_DEALER => 'DEALER',
            ZMQ::SOCKET_ROUTER => 'ROUTER',
            ZMQ::SOCKET_PUSH => 'PUSH',
            ZMQ::SOCKET_PULL => 'PULL',
            ZMQ::SOCKET_REQ => 'REQ',
            ZMQ::SOCKET_REP => 'REP',
            default => 'UNKNOWN'
        };
    }

    private function getDefaultConnection(): string
    {
        return $this->config['default'] ?? array_key_first($this->config['connections']);
    }

    private function logError(string $message, \Exception $e): void
    {
        if ($this->config['debug_logs'] ?? false) {
            logger()->error($message, [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
