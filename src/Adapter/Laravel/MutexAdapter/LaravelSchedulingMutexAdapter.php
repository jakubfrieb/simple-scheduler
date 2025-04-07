<?php

namespace App\Adapter\Laravel\MutexAdapter;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\SchedulingMutex;
use App\Adapter\MutexInterface;
use DateTimeInterface;

class LaravelSchedulingMutexAdapter implements SchedulingMutex
{
    protected $mutex;

    public function __construct(MutexInterface $mutex)
    {
        $this->mutex = $mutex;
    }

    public function create(Event $event, DateTimeInterface $time)
    {
        return $this->mutex->acquire($event->mutexName() . $time->format('Hi'));
    }

    public function exists(Event $event, DateTimeInterface $time)
    {
        return $this->mutex->exists($event->mutexName() . $time->format('Hi'));
    }
}