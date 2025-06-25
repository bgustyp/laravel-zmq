<?php

namespace Bgustyp\LaravelZmq;

use Illuminate\Support\ServiceProvider;
use Bgustyp\LaravelZmq\Connector\ZmqPublish;
use Bgustyp\LaravelZmq\Connector\ZmqSubscribe;
use Bgustyp\LaravelZmq\Connector\ZmqDealer;
use Bgustyp\LaravelZmq\Connector\ZmqRouter;
use Bgustyp\LaravelZmq\Broadcasting\Broadcaster\ZmqBroadcaster;
use Bgustyp\LaravelZmq\Console\Commands\ZmqTestCommand;
use Bgustyp\LaravelZmq\Console\Commands\ZmqServerCommand;
use Bgustyp\LaravelZmq\Console\Commands\ZmqMonitorCommand;

/**
 * Class ZmqServiceProvider
 * @package Bgustyp\LaravelZmq
 */
class ZmqServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([__DIR__ . '/../config/zmq.php' => config_path('zmq.php')]);

        $this->app->make('Illuminate\Contracts\Broadcasting\Factory')
            ->extend('zmq', function ($app) {
                return new ZmqBroadcaster($this->app['zmq']);
            });

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                ZmqTestCommand::class,
                ZmqServerCommand::class,
                ZmqMonitorCommand::class,
            ]);
        }
    }

    public function register()
    {
        $this->app->singleton('zmq', function ($app) {
            return new Zmq();
        });

        $this->app->singleton('zmq.connection.publish', function ($app) {
            return new ZmqPublish();
        });

        $this->app->singleton('zmq.connection.subscribe', function ($app) {
            return new ZmqSubscribe();
        });

        $this->app->singleton('zmq.connection.dealer', function ($app) {
            return new ZmqDealer();
        });

        $this->app->singleton('zmq.connection.router', function ($app) {
            return new ZmqRouter();
        });
    }

    /**
     * @return array
     */
    public function provides()
    {
        return ['zmq', 'zmq.connection.subscribe', 'zmq.connection.publish', 'zmq.connection.dealer', 'zmq.connection.router'];
    }
}
