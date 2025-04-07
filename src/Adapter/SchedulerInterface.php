<?php

namespace App\Adapter;

interface SchedulerInterface
{
    /**
     * Register a new task to be scheduled (this will create new).
     */
    public function task(string $command): TaskInterface;

    /**
     * Add the task to be scheduled (this will only add instance).
     */
    public function addTask(TaskInterface $task): void;

    /**
     * Run all due tasks.
     */
    public function run(): array;

    /**
     * Set storage for tracking task execution
     */
    public function setStorage(StorageInterface $storage): self;

    /**
     * Set mutex for controlling concurrent task execution
     */
    public function setMutex(MutexInterface $mutex): self;

    /**
     * Get mutex for controlling concurrent task execution
     */
    public function getMutex(): ?MutexInterface;
}
