<?php

namespace App\Adapter;

interface MutexInterface
{
    /**
     * Get the name of the mutex.
     */
    public function getMutexType(): string;
    /**
     * Acquire a lock for the given task.
     */
    public function acquire(string $taskId): bool;

    /**
     * Release a lock for the given task.
     */
    public function release(string $taskId): bool;

    /**
     * Check if a lock exists for the given task.
     */
    public function exists(string $taskId): bool;
}
