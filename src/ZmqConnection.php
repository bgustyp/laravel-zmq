<?php

declare(strict_types=1);

namespace Bgustyp\LaravelZmq;

use ZMQ;
use ZMQContext;
use ZMQSocket;
use Bgustyp\LaravelZmq\Exceptions\ZmqException;
use Bgustyp\LaravelZmq\Support\LibZmqCompatibility;

class ZmqConnection
{
    private array $config;
    private ?ZMQSocket $socket = null;
    private ZMQContext $context;

    public function __construct(array $config, ZMQContext $context)
    {
        $this->config = $config;
        $this->context = $context;
        $this->validateLibZmqCompatibility();
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
            $this->applySecurity();

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
        $supportedOptions = LibZmqCompatibility::getSupportedSocketOptions();

        // Basic options
        if (isset($this->config['linger'])) {
            $this->socket->setSockOpt(ZMQ::SOCKOPT_LINGER, $this->config['linger']);
        }

        if (isset($this->config['identity']) && $this->config['method'] === ZMQ::SOCKET_DEALER) {
            $this->socket->setSockOpt(ZMQ::SOCKOPT_IDENTITY, $this->config['identity']);
        }

        if (isset($this->config['hwm'])) {
            $this->socket->setSockOpt(ZMQ::SOCKOPT_SNDHWM, $this->config['hwm']);
            $this->socket->setSockOpt(ZMQ::SOCKOPT_RCVHWM, $this->config['hwm']);
        }

        // libzmq 4.3.4+ specific options
        if (LibZmqCompatibility::isLibZmq434OrHigher()) {
            // Connection timeout
            if (isset($this->config['connect_timeout']) && isset($supportedOptions['ZMQ::SOCKOPT_CONNECT_TIMEOUT'])) {
                $this->socket->setSockOpt(ZMQ::SOCKOPT_CONNECT_TIMEOUT, $this->config['connect_timeout']);
            }

            // Heartbeat settings
            $this->setHeartbeatOptions($supportedOptions);
        }
    }

    private function setHeartbeatOptions(array $supportedOptions): void
    {
        if (!isset($this->config['heartbeat'])) {
            return;
        }

        $heartbeat = $this->config['heartbeat'];

        if (isset($heartbeat['interval']) && isset($supportedOptions['ZMQ::SOCKOPT_HEARTBEAT_IVL'])) {
            $this->socket->setSockOpt(ZMQ::SOCKOPT_HEARTBEAT_IVL, $heartbeat['interval']);
        }

        if (isset($heartbeat['timeout']) && isset($supportedOptions['ZMQ::SOCKOPT_HEARTBEAT_TIMEOUT'])) {
            $this->socket->setSockOpt(ZMQ::SOCKOPT_HEARTBEAT_TIMEOUT, $heartbeat['timeout']);
        }

        if (isset($heartbeat['ttl']) && isset($supportedOptions['ZMQ::SOCKOPT_HEARTBEAT_TTL'])) {
            $this->socket->setSockOpt(ZMQ::SOCKOPT_HEARTBEAT_TTL, $heartbeat['ttl']);
        }
    }

    private function applySecurity(): void
    {
        if (!LibZmqCompatibility::isCurveSecurityAvailable()) {
            return;
        }

        $security = $this->config['security'] ?? [];

        // CURVE security
        if (isset($security['curve']['enabled']) && $security['curve']['enabled']) {
            $this->applyCurveSecurity($security['curve']);
        }

        // ZAP domain
        if (isset($security['zap']['domain']) && !empty($security['zap']['domain'])) {
            $this->socket->setSockOpt(ZMQ::SOCKOPT_ZAP_DOMAIN, $security['zap']['domain']);
        }
    }

    private function applyCurveSecurity(array $curveConfig): void
    {
        if (!LibZmqCompatibility::isCurveSecurityAvailable()) {
            throw new ZmqException('CURVE security is not available');
        }

        $isServer = in_array($this->config['method'], [ZMQ::SOCKET_ROUTER, ZMQ::SOCKET_PUB, ZMQ::SOCKET_REP]);

        if ($isServer) {
            // Server configuration
            $this->socket->setSockOpt(ZMQ::SOCKOPT_CURVE_SERVER, 1);

            if (!empty($curveConfig['server_key'])) {
                $this->socket->setSockOpt(ZMQ::SOCKOPT_CURVE_SECRETKEY, $curveConfig['server_key']);
            }
        } else {
            // Client configuration
            $this->socket->setSockOpt(ZMQ::SOCKOPT_CURVE_SERVER, 0);

            if (!empty($curveConfig['public_key'])) {
                $this->socket->setSockOpt(ZMQ::SOCKOPT_CURVE_PUBLICKEY, $curveConfig['public_key']);
            }

            if (!empty($curveConfig['secret_key'])) {
                $this->socket->setSockOpt(ZMQ::SOCKOPT_CURVE_SECRETKEY, $curveConfig['secret_key']);
            }

            if (!empty($curveConfig['server_key'])) {
                $this->socket->setSockOpt(ZMQ::SOCKOPT_CURVE_SERVERKEY, $curveConfig['server_key']);
            }
        }
    }

    private function validateLibZmqCompatibility(): void
    {
        $validation = LibZmqCompatibility::validateConfiguration($this->config);

        if (!$validation['valid']) {
            throw new ZmqException('Configuration validation failed: ' . implode(', ', $validation['errors']));
        }

        // Log warnings if debug is enabled
        if (!empty($validation['warnings']) && ($this->config['debug_logs'] ?? false)) {
            foreach ($validation['warnings'] as $warning) {
                logger()->warning("[ZMQ] {$warning}");
            }
        }
    }

    private function getDefaultAction(): string
    {
        $bindSockets = [ZMQ::SOCKET_PUB, ZMQ::SOCKET_ROUTER, ZMQ::SOCKET_PULL, ZMQ::SOCKET_REP];
        return in_array($this->config['method'], $bindSockets) ? 'bind' : 'connect';
    }
}
