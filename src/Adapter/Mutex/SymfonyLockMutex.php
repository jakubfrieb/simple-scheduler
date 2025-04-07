<?php

namespace App\Adapter\Mutex;

use App\Adapter\MutexInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

class SymfonyLockMutex implements MutexInterface
{
    private string $mutexType = 'symfony';

    private LockFactory $lockFactory;
    private array $locks = [];

    public function __construct(?string $lockDir = null)
    {
        $lockDir = $lockDir ?? sys_get_temp_dir();
        $store = new FlockStore($lockDir);
        $this->lockFactory = new LockFactory($store);
    }

    public function getMutexType(): string
    {
        return $this->mutexType;
    }

    public function acquire(string $taskId): bool
    {
        if ($this->exists($taskId)) {
            return false;
        }

        $lock = $this->lockFactory->createLock($taskId);

        if ($lock->acquire(false)) {
            $this->locks[$taskId] = $lock;
            return true;
        }

        return false;
    }

    public function release(string $taskId): bool
    {
        if (!isset($this->locks[$taskId])) {
            return true;
        }

        $this->locks[$taskId]->release();
        unset($this->locks[$taskId]);

        return true;
    }

    public function exists(string $taskId): bool
    {
        if (isset($this->locks[$taskId])) {
            return true;
        }

        $lock = $this->lockFactory->createLock($taskId);
        return !$lock->acquire(false);
    }
}
