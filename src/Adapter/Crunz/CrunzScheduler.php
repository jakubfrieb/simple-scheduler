<?php

namespace App\Adapter\Crunz;

use App\Adapter\MutexInterface;
use App\Adapter\SchedulerInterface;
use App\Adapter\StorageInterface;
use App\Adapter\TaskInterface;
use Crunz\Event;
use Crunz\Schedule as CrunzSchedule;
use Exception;

class CrunzScheduler implements SchedulerInterface
{
    private $schedule;
    private $tasks = [];
    private ?StorageInterface $storage = null;
    private ?MutexInterface $mutex = null;

    public function __construct()
    {
        $this->schedule = new CrunzSchedule();
    }

    public function task(string $command): TaskInterface
    {
        $task = new CrunzTask($this->schedule, $command);
        $this->tasks[] = $task;
        return $task;
    }

    public function addTask(TaskInterface $task): void
    {
        $this->tasks[] = $task;
    }

    public function run(): array
    {
        $timeZone = new \DateTimeZone('UTC'); // Adjust the timezone as needed
        $dateTime = new \DateTime('now', $timeZone);
        $dueTasks = [];

        // Get due events
        foreach ($this->tasks as $task) {
            if($task->isDue($dateTime)) {
                $dueTasks[] = $task;
            };
        }

        // Debug information
        echo "Found " . count($dueTasks) . " due events\n";

        $results = [];

        foreach ($dueTasks as $task) {
            // Get command from event
            $command = $task->getEvent()->getCommand();
            $taskId = md5($command); // Generate consistent ID based on command

            try {
                echo "Running task: " . $command . "\n";

                // Skip if mutex is active for this task
                if ($this->mutex && $this->mutex->exists($taskId)) {
                    // Store result
                    $results[$taskId] = [
                        'status' => 'skipping',
                        'output' => 'Task is locked by mutex'
                    ];
                    echo "Task is locked by mutex, skipping\n";
                    continue;
                }

                // Try to acquire mutex
                if ($this->mutex && !$this->mutex->acquire($taskId)) {
                    echo "Failed to acquire mutex, skipping\n";
                    continue;
                }

                // Remove escaped quotes if present
                $command = str_replace("\'", "'", $command);

                echo "Executing command: " . $command . "\n";

                if (!$this->mutex) {
                    // set prevent overlapping if own mutex is not used
                    $task->getEvent()->preventOverlapping(); // <- in this place we can also pass our mutex storage
                }

                $task->execute();

                // Store result
                $results[$taskId] = [
                    'status' => 'processing',
                    'output' => ''
                ];

                // Release the mutex
                if ($this->mutex) {
                    $this->mutex->release($taskId);
                }
            } catch (Exception $e) {
                // Release mutex on error
                if ($this->mutex) {
                    $this->mutex->release($taskId);
                }

                echo "Error: " . $e->getMessage() . "\n";

                // Store error result
                $results[$taskId] = [
                    'status' => 'error',
                    'output' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Log an error message.
     *
     * @param string $message
     */
    private function logError(string $message): void
    {
        // Simple logging to a file or output
        error_log($message, 3, '/../../logs/crunz.log'); // Adjust the path as needed
    }

    public function setStorage(StorageInterface $storage): self
    {
        $this->storage = $storage;
        return $this;
    }

    public function setMutex(MutexInterface $mutex): self
    {
        $this->mutex = $mutex;
        return $this;
    }

    public function getMutex(): MutexInterface
    {
        return $this->mutex;
    }

}
