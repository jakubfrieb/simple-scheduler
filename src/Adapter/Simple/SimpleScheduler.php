<?php

namespace App\Adapter\Simple;

use App\Adapter\MutexInterface;
use App\Adapter\SchedulerInterface;
use App\Adapter\StorageInterface;
use App\Adapter\TaskInterface;

class SimpleScheduler implements SchedulerInterface
{
    private TaskRepository $repository;
    private ?StorageInterface $storage = null;
    private ?MutexInterface $mutex = null;
    private array $tasks = [];

    public function __construct(TaskRepository $repository)
    {
        $this->repository = $repository;
    }


    public function task(string $command): TaskInterface
    {
        // TODO to review if I should save task to database here
        $task = new Task($command);
        $this->repository->save($task);
        $this->tasks[] = $task;
        return $task;
    }

    public function addTask(TaskInterface $task): void
    {
        $this->tasks[] = $task;
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

    public function run(): array
    {
        return $this->runDueTasks();
    }

    public function runDueTasks(): array
    {
        $now = new \DateTime();

        //$dueTasks = $this->repository->findDue($now);

        $dueTasks = array_filter($this->tasks, fn (Task $task) => $task->isDue($now));

        $results = [];

        foreach ($dueTasks as $task) {
            // Use mutex to check if task is already running
            if ($this->mutex && $this->mutex->exists($task->getId())) {
                $results[$task->getId()] = [
                    'status' => 'skipped',
                    'output' => 'Task is locked by mutex'
                ];
                continue;
            }

            // check if task has correct status in task table
            $taskStatus = $this->repository->getTaskStatus($task->getId());
            if (!empty($taskStatus) && !in_array($taskStatus, ['pending', 'completed'])) {
                $results[$task->getId()] = [
                    'status' => 'skipped',
                    'output' => "Task has status '$taskStatus' and is not ready to run"
                ];
                continue;
            }
            // run task and return result
            $results[$task->getId()] = $this->runTask($task, $now);
        }

        return $results;
    }

    protected function runTask(Task $task, \DateTime $now): array
    {
        $taskId = $task->getId();
        $originalCommand = $task->getCommand();
        $mutexType =$this->getMutex()->getMutexType();

        // Try to acquire mutex lock again
        if ($this->mutex && !$this->mutex->acquire($taskId)) {
            return [
                'status' => 'skipped',
                'output' => 'Failed to acquire mutex lock'
            ];
        }

        try {
            // Record execution start
            $runId = $this->repository->recordExecution(
                $taskId,
                $now,
                null,
                "Process starting..."
            );

            // Create command for PHP wrapper
            $wrapperPath = escapeshellarg(__DIR__ . '/_wrapper.php');
            $escapedRunId = escapeshellarg((string)$runId);

            // Build the command with proper spacing and quoting
            $command = PHP_BINARY . ' ' . $wrapperPath . ' '.$taskId. ' ' . $escapedRunId . ' ' .$mutexType .' '.$originalCommand;

            // Run process in background
            if (PHP_OS_FAMILY !== 'Windows') {
                $bgCommand = "nohup $command > /dev/null 2>&1 & echo $!";
            } else {
                $bgCommand = "start /B $command";
            }
            // Run the command
            exec($bgCommand);

            return [
                'status' => 'running',
                'output' => "Process started with run ID: $runId"
            ];
        } catch (\Exception $e) {
            // Release mutex on error
            if ($this->mutex) {
                $this->mutex->release($taskId);
            }

            // Update execution record
            if (isset($runId)) {
                $this->repository->markTaskCompleted($runId, 'error', $e->getMessage());
            } else {
                $this->repository->recordExecution(
                    $taskId,
                    $now,
                    'error',
                    $e->getMessage()
                );
            }

            return [
                'status' => 'error',
                'output' => $e->getMessage()
            ];
        }
    }
}
