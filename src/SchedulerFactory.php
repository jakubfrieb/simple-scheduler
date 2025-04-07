<?php

namespace App;

use App\Adapter\Crunz\CrunzScheduler;
use App\Adapter\Crunz\CrunzTaskLoader;
use App\Adapter\Laravel\ContainerBootstrap;
use App\Adapter\Laravel\LaravelScheduler;
use App\Adapter\Laravel\LaravelTaskLoader;
use App\Adapter\Mutex\DatabaseMutex;
use App\Adapter\Mutex\FileMutex;
use App\Adapter\Mutex\SymfonyLockMutex;
use App\Adapter\MutexInterface;
use App\Adapter\SchedulerInterface;
use App\Adapter\Simple\SimpleTaskLoader;
use App\Adapter\Simple\SimpleScheduler;
use App\Adapter\Simple\TaskRepository;
use App\Adapter\TaskLoaderInterface;
use Exception;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Container\BindingResolutionException;

class SchedulerFactory
{
    /**
     * @throws Exception
     */
    public static function create(string $type = 'simple', ?string $mutexType = null): SchedulerInterface
    {
        $scheduler = self::createScheduler($type, $mutexType);
        $loader = self::createLoader($type);
        // If empty is used default from scheduler
        if ($mutexType){
            $mutex = self::createMutex($mutexType);
            // Set mutex on scheduler
            $scheduler->setMutex($mutex);
        }


        // Load tasks into the scheduler
        $loader->loadTasks($scheduler);

        return $scheduler;
    }

    /**
     * @throws BindingResolutionException
     * @throws Exception
     */
    public static function createScheduler(string $type, ?string $mutexType = null): SchedulerInterface
    {
        switch ($type) {
            case 'simple':
                $repository = new TaskRepository();
                return new SimpleScheduler($repository);
            case 'crunz':
                return new CrunzScheduler();
            case 'laravel':
                // Initialize Laravel container with mutex
                // TODO I had to pass mutex instance to initialize, which is different approach ...
                $mutex = $mutexType ? self::createMutex($mutexType) : null;
                $container = ContainerBootstrap::initialize($mutex);

                // Get the schedule instance from the container
                $schedule = $container->make(Schedule::class);
                return new LaravelScheduler($container, $schedule);
            default:
                throw new Exception("Unknown scheduler type: {$type}");
        }
    }

    /**
     * @throws Exception
     */
    public static function createLoader(string $type): TaskLoaderInterface
    {
        switch ($type) {
            case 'simple':
                $repository = new TaskRepository();
                return new SimpleTaskLoader($repository);
            case 'crunz':
                return new CrunzTaskLoader();
            case 'laravel':
                return new LaravelTaskLoader();
            default:
                throw new Exception("Unknown loader type: {$type}");
        }
    }

    /**
     * @throws Exception
     */
    public static function createMutex(string $type): MutexInterface
    {
        switch ($type) {
            case 'file':
                return new FileMutex();
            case 'database':
                return new DatabaseMutex();
            case 'symfony':
                return new SymfonyLockMutex();
            default:
                throw new Exception("Unknown mutex type: {$type}");
        }
    }

    /**
     * @throws Exception
     */
    public static function getLoader(string $type): TaskLoaderInterface
    {
        return self::createLoader($type);
    }
}
