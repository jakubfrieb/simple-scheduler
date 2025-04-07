<?php

namespace App\Adapter\Crunz;

use App\Adapter\TaskInterface;
use Crunz\Schedule;

class CrunzTask implements TaskInterface
{
    private $schedule;
    private $command;
    private $id;
    private $taskDescription;
    private $event;

    public function __construct($schedule, string $command)
    {
        $this->schedule = $schedule;
        $this->command = $command;
        $this->id = uniqid(); // Generate a unique ID for the task

        // Create a Crunz event for this task
        if ($schedule instanceof Schedule) {
            $this->event = $schedule->run($command);
        }
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

    public function description(string $description): self
    {
        $this->taskDescription = $description;
        if ($this->event) {
            $this->event->description($description);
        }
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->taskDescription ?? null;
    }

    public function isDue(?\DateTimeInterface $now = null): bool
    {
        if (!$this->event) {
            return false;
        }

        // Use Crunz's native isDue method
        return $this->event->isDue($now->getTimezone());
    }

    public function execute(): void
    {
        if ($this->event) {
            $this->event->start();
        } else {
            // Fallback if no event is available
            exec($this->command);
        }
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the underlying Crunz event
     */
    public function getEvent()
    {
        return $this->event;
    }
}
