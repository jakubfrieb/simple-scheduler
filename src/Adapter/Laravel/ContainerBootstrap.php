<?php

namespace App\Adapter\Laravel;

use App\Adapter\Laravel\MutexAdapter\LaravelMutexAdapter;
use App\Adapter\Laravel\MutexAdapter\LaravelSchedulingMutexAdapter;
use App\Adapter\MutexInterface;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Console\Scheduling\CacheEventMutex;
use Illuminate\Console\Scheduling\CacheSchedulingMutex;
use Illuminate\Console\Scheduling\EventMutex;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Console\Scheduling\SchedulingMutex;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Factory as CacheFactory;

class ContainerBootstrap
{
    public static function initialize(?MutexInterface $mutex = null): Container
    {
        // Define Laravel helper functions in the global namespace
        if (!function_exists('base_path')) {
            eval('function base_path($path = "") { 
                return rtrim(dirname(__DIR__, 3), "/") . ($path ? "/" . $path : "");
            }');
        }

        if (!function_exists('storage_path')) {
            eval('function storage_path($path = "") { 
                return rtrim(dirname(__DIR__, 3), "/") . "/storage" . ($path ? "/" . $path : "");
            }');
        }

        if (!function_exists('app')) {
            eval('function app($abstract = null) { 
                $container = \Illuminate\Container\Container::getInstance();
                return $abstract ? $container->make($abstract) : $container;
            }');
        }

        $container = new Container();
        Container::setInstance($container);

        // Add isDownForMaintenance method to container by extending it
        $container = new class () extends Container {
            public function isDownForMaintenance()
            {
                return false;
            }

            public function environment()
            {
                return 'production';
            }
        };
        Container::setInstance($container);

        // Register the cache components properly
        $container->singleton('config', function () {
            return [
                'cache.default' => 'array',
                'cache.stores.array' => [
                    'driver' => 'array',
                ],
            ];
        });

        // Register the cache manager
        $container->singleton('cache', function ($container) {
            return new CacheManager($container);
        });

        // Register the cache factory
        $container->singleton(CacheFactory::class, function ($container) {
            return $container->make('cache');
        });

        // Register the cache repository
        $container->singleton('cache.store', function ($container) {
            return $container->make('cache')->driver();
        });

        if ($mutex) {
            $container->singleton(EventMutex::class, function ($container) use ($mutex) {
                return new LaravelMutexAdapter($mutex);
            });
            $container->singleton(SchedulingMutex::class, function ($container) use ($mutex) {
                return new LaravelSchedulingMutexAdapter($mutex);
            });
        } else {
            // Register the event and scheduling mutexes
            $container->singleton(EventMutex::class, function ($container) {
                return new CacheEventMutex($container->make(CacheFactory::class));
            });

            $container->singleton(SchedulingMutex::class, function ($container) {
                return new CacheSchedulingMutex($container->make(CacheFactory::class));
            });
        }

        // Register the Schedule
        $container->singleton(Schedule::class, function () {
            return new Schedule();
        });

        return $container;
    }
}
