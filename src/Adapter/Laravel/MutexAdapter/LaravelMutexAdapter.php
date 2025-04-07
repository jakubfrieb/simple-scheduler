<?php

namespace App\Adapter\Laravel\MutexAdapter;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\EventMutex;
use App\Adapter\MutexInterface;

class LaravelMutexAdapter implements EventMutex
{
    protected $mutex;

    public function __construct(MutexInterface $mutex)
    {
        $this->mutex = $mutex;
    }

    public function create(Event $event)
    {
        return $this->mutex->acquire($event->mutexName());
    }

    public function exists(Event $event)
    {
        return $this->mutex->exists($event->mutexName());
    }

    public function forget(Event $event)
    {
        $this->mutex->release($event->mutexName());
    }
}
