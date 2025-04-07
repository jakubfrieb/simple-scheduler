<?php

namespace App\Adapter;

interface TaskInterface
{
    /**
     * Set the task to run daily.
     */
    public function daily(): self;

    /**
     * Set the task to run hourly.
     */
    public function hourly(): self;

    /**
     * Set the task to run at a specific time.
     */
    public function at(string $time): self;

    /**
     * Set the task to run every minute.
     */
    public function everyMinute(): self;

    /**
     * Determine if the task is due to run.
     *
     * @param \DateTimeInterface|null $now Optional date to check against
     * @return bool
     */
    public function isDue(?\DateTimeInterface $now = null): bool;

    /**
     * Execute the task.
     */
    public function execute(): void;
}
