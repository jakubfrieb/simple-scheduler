<?php

namespace App\Adapter\Simple;

use App\Adapter\SchedulerInterface;
use App\Adapter\TaskLoaderInterface;

class SimpleTaskLoader implements TaskLoaderInterface
{
    private TaskRepository $repository;

    public function __construct(TaskRepository $repository)
    {
        $this->repository = $repository;
    }

    public function loadTasks(SchedulerInterface $scheduler): void
    {
        $tasks = $this->repository->findAll();

        foreach ($tasks as $task) {
            // Set frequency based on expression
            switch ($task->getExpression()) {
                case '0 0 * * *':
                    $task->daily();
                    break;
                case '0 * * * *':
                    $task->hourly();
                    break;
                case '* * * * *':
                    $task->everyMinute();
                    break;
                default:
                    // Handle custom expressions
                    if (preg_match('/^(\d+) (\d+) \* \* \*$/', $task->getExpression(), $matches)) {
                        $task->at(sprintf('%02d:%02d', $matches[2], $matches[1]));
                    }
            }

            if ($task->getDescription()) {
                $task->description($task->getDescription());
            }

            // Add the task to the scheduler
            $scheduler->addTask($task);
        }
    }

    public function addTask(string $command, string $frequency, ?string $description = null): void
    {
        $task = new Task($command);

        switch ($frequency) {
            case 'daily':
                $task->daily();
                break;
            case 'hourly':
                $task->hourly();
                break;
            case 'everyMinute':
                $task->everyMinute();
                break;
            default:
                if (strpos($frequency, 'at:') === 0) {
                    $time = substr($frequency, 3);
                    $task->at($time);
                }
        }

        if ($description) {
            $task->description($description);
        }

        $this->repository->save($task);
    }

    public function removeTask(string $taskId): bool
    {
        return $this->repository->delete($taskId);
    }
}
