<?php

declare(strict_types=1);

namespace Bgustyp\LaravelZmq\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Bgustyp\LaravelZmq\ZmqManager;

class ZmqBroadcaster extends Broadcaster
{
    public function __construct(
        private ZmqManager $zmqManager,
        private string $connection = 'publish'
    ) {}

    public function auth($request)
    {
        return true;
    }

    public function validAuthenticationResponse($request, $result)
    {
        return $result;
    }

    public function broadcast(array $channels, $event, array $payload = [])
    {
        try {
            $message = json_encode([
                'event' => $event,
                'data' => $payload,
                'timestamp' => now()->toISOString(),
            ]);

            foreach ($channels as $channel) {
                $success = $this->zmqManager->publish($channel, $message, $this->connection);

                if (!$success) {
                    throw new BroadcastException("Failed to broadcast to channel: {$channel}");
                }
            }
        } catch (\Exception $e) {
            throw new BroadcastException("ZMQ broadcast failed: " . $e->getMessage(), 0, $e);
        }
    }
}
