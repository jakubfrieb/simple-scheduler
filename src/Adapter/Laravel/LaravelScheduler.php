<?php

namespace App\Adapter\Laravel;

use App\Adapter\MutexInterface;
use App\Adapter\SchedulerInterface;
use App\Adapter\StorageInterface;
use App\Adapter\TaskInterface;
use Exception;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Container\Container;
use Throwable;

class LaravelScheduler implements SchedulerInterface
{
    private Container $container;
    private Schedule $schedule;

    public function __construct(Container $container, Schedule $schedule)
    {
        $this->container = $container;
        $this->schedule = $schedule;
    }

    /**
     * Run all due tasks.
     *
     * @return array
     * @throws Throwable
     */
    public function run(): array
    {
        echo "Total events in schedule: " . count($this->schedule->events()) . "\n";

        // Print out the events for debugging
        $i = 1;
        foreach ($this->schedule->events() as $event) {
            echo "Event " . $i++ . ": " . $event->command . " (Expression: " . $event->expression . ")\n";
        }

        return $this->runDueEvents();
    }

    /**
     * Run all due events.
     *
     * @return array
     * @throws Throwable
     */
    protected function runDueEvents() : array
    {
        // Get due events - use the container directly since we've extended it
        // todo type mismatch for dueEvents
        $dueEvents = $this->schedule->dueEvents($this->container);

        $results = [];

        foreach ($dueEvents as $event) {
            $this->runEvent($event);
            $results[] = [
                'status' => 'completed',
                'output' => $event->command
            ];
        }

        return $results;
    }

    /**
     * Run an event.
     *
     * @param Event $event
     * @return void
     * @throws Throwable
     */
    protected function runEvent(Event $event):void
    {
        echo "Running: " . $event->command . "\n";

        try {
            $event->withoutOverlapping()->run($this->container);
        } catch (Exception $e) {
            echo "Error running event: " . $e->getMessage() . "\n";
        }
    }

    /**
     * @param string $command
     * @return TaskInterface
     */
    public function task(string $command): TaskInterface
    {
        return new LaravelTask($this->schedule, $command);
    }

    /**
     * @param StorageInterface $storage
     * @return SchedulerInterface
     */
    public function setStorage(StorageInterface $storage): SchedulerInterface
    {
        // Laravel scheduler doesn't use our storage interface
        return $this;
    }

    public function getEvents(): array
    {
        return $this->schedule->events();
    }

    public function setMutex(MutexInterface $mutex): SchedulerInterface
    {
        // Laravel has its own mutex implementation
        return $this;
    }

    public function getMutex(): ?MutexInterface
    {
       throw new Exception("Laravel scheduler doesn't use our mutex interface");
    }

    /**
     * This is used to add tasks from kernel file
     * also this will load events in Laravel format
     * @param Kernel $kernel
     * @return void
     */
    public function addTasksFromKernel(Kernel $kernel): void {
          $kernel->schedule($this->schedule);
    }

    public function addTask(TaskInterface $task): void
    {
        // Laravel scheduler doesn't use our task interface
    }
}
