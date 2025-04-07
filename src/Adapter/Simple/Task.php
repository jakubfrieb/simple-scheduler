<?php

namespace App\Adapter\Simple;

use App\Adapter\TaskInterface;

class Task implements TaskInterface
{
    private string $id;
    private string $command;
    private string $expression = '* * * * *';
    private string $description = '';

    public function __construct(string $command)
    {
        $this->command = $command;
        $this->id = md5($command . uniqid());
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function daily(): self
    {
        $this->expression = '0 0 * * *';
        return $this;
    }

    public function hourly(): self
    {
        $this->expression = '0 * * * *';
        return $this;
    }

    public function at(string $time): self
    {
        list($hour, $minute) = explode(':', $time);
        $this->expression = "{$minute} {$hour} * * *";
        return $this;
    }

    public function everyMinute(): self
    {
        $this->expression = '* * * * *';
        return $this;
    }

    public function isDue(\DateTimeInterface $now = null): bool
    {
        // Use current time if none provided
        $now = $now ?? new \DateTime();

        // Implementation from the existing code
        $minute = (int)$now->format('i');
        $hour = (int)$now->format('H');

        if ($this->expression === '* * * * *') {
            return true; // Every minute
        }

        if ($this->expression === '0 * * * *') {
            return $minute === 0; // Every hour at 00 minutes
        }

        if ($this->expression === '0 0 * * *') {
            return $hour === 0 && $minute === 0; // Every day at 00:00
        }

        // Specific time (e.g., "30 14 * * *" for 14:30)
        if (preg_match('/^(\d+) (\d+) \* \* \*$/', $this->expression, $matches)) {
            return (int)$matches[1] === $minute && (int)$matches[2] === $hour;
        }

        return false;
    }

    public function execute(): void
    {
        // Simple implementation - execute the command
        exec($this->command);
    }
}
