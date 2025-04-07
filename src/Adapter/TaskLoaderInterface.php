<?php

namespace App\Adapter;

interface TaskLoaderInterface
{
    /**
     * Load all tasks into the scheduler.
     */
    public function loadTasks(SchedulerInterface $scheduler): void;

    /**
     * Add a new task to the storage.
     */
    public function addTask(string $command, string $frequency, ?string $description = null): void;

    /**
     * Remove a task from storage.
     */
    public function removeTask(string $taskId): bool;
}
