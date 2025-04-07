<?php

namespace App\Adapter\Laravel;

use App\Adapter\TaskInterface;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Container\Container;

class LaravelTask implements TaskInterface
{
    private Schedule $schedule;
    private string $command;
    private ?Event $event = null;
    private string $id;

    public function __construct(Schedule $schedule, string $command)
    {

        $this->schedule = $schedule;
        $this->command = $command;
        $this->id = md5($command . uniqid());

        // Create the event based on command type
        if (strpos($command, 'php ') === 0) {
            // Assume it's an Artisan command if it starts with php
            $this->event = $this->schedule->exec($command);
        } else {
            // Otherwise treat as a shell command
            $this->event = $this->schedule->exec($command);
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function description(string $description): self
    {
        if ($this->event) {
            $this->event->description($description);
        }
        return $this;
    }

    public function daily(): self
    {
        if ($this->event) {
            $this->event->daily();
        }
        return $this;
    }

    public function hourly(): self
    {
        if ($this->event) {
            $this->event->hourly();
        }
        return $this;
    }

    public function at(string $time): self
    {
        if ($this->event) {
            $this->event->at($time);
        }
        return $this;
    }

    public function everyMinute(): self
    {
        if ($this->event) {
            $this->event->everyMinute();
        }
        return $this;
    }

    public function isDue(?\DateTimeInterface $now = null): bool
    {
        if (!$this->event) {
            return false;
        }

        return $this->event->isDue(Container::getInstance());
    }

    public function execute(): void
    {
        if ($this->event) {
            $this->event->run(Container::getInstance());
        }
    }
}
