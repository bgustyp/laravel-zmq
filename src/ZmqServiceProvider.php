<?php

declare(strict_types=1);

namespace Bgustyp\LaravelZmq;

use Illuminate\Support\ServiceProvider;
use Illuminate\Broadcasting\BroadcastManager;
use Bgustyp\LaravelZmq\Broadcasting\ZmqBroadcaster;
use Bgustyp\LaravelZmq\Console\{
    ZmqTestCommand,
    ZmqServerCommand,
    ZmqMonitorCommand,
    ZmqVersionCommand
};

class ZmqServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/zmq.php',
            'zmq'
        );

        $this->app->singleton('zmq.manager', function ($app) {
            return new ZmqManager($app['config']['zmq']);
        });

        $this->app->alias('zmq.manager', ZmqManager::class);

        $this->registerCommands();
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/zmq.php' => config_path('zmq.php'),
        ], 'zmq-config');

        // Broadcasting integration
        $this->app->make(BroadcastManager::class)->extend('zmq', function ($app, $config) {
            return new ZmqBroadcaster(
                $app->make('zmq.manager'),
                $config['connection'] ?? 'publish'
            );
        });
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ZmqTestCommand::class,
                ZmqServerCommand::class,
                ZmqMonitorCommand::class,
                ZmqVersionCommand::class,
            ]);
        }
    }
}
