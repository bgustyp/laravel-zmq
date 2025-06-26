<?php

declare(strict_types=1);

namespace Bgustyp\LaravelZmq\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Bgustyp\LaravelZmq\ZmqConnection connection(?string $name = null)
 * @method static bool publish(string $channel, mixed $data, ?string $connection = null)
 * @method static bool send(string $message, ?string $connection = null)
 * @method static string|null receive(?string $connection = null, ?int $timeout = null)
 * @method static array healthCheck()
 * @method static void closeAll()
 */
class ZmqManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'zmq.manager';
    }
}
