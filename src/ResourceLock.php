<?php
declare(strict_types=1);

namespace FurqanSiddiqui\SemaphoreEmulator;

use FurqanSiddiqui\SemaphoreEmulator\Exception\ConcurrentRequestBlocked;
use FurqanSiddiqui\SemaphoreEmulator\Exception\ConcurrentRequestTimeout;
use FurqanSiddiqui\SemaphoreEmulator\Exception\ResourceLockException;

/**
 * Class ResourceLock
 * @package FurqanSiddiqui\SemaphoreEmulator
 */
class ResourceLock
{
    /** @var SemaphoreEmulator */
    private $emulator;
    /** @var bool */
    private $isLocked;
    /** @var false|resource */
    private $fp;
    /** @var null|float */
    private $lastTimestamp;
    /** @var bool */
    private $autoReleaseSet;

    /**
     * ResourceLock constructor.
     * @param SemaphoreEmulator $emulator
     * @param string $resourceIdentifier
     * @param float|null $concurrentReqInterval
     * @param int $concurrentReqTimeout
     * @throws ConcurrentRequestBlocked
     * @throws ResourceLockException
     */
    public function __construct(SemaphoreEmulator $emulator, string $resourceIdentifier, ?float $concurrentReqInterval = null, int $concurrentReqTimeout = 30)
    {
        if (!preg_match('/^\w+$/', $resourceIdentifier)) {
            throw new \InvalidArgumentException('Invalid resource identifier for semaphore emulator');
        }

        $this->emulator = $emulator;
        $this->isLocked = false;
        $this->autoReleaseSet = false;

        $lockFilePath = $this->emulator->dir()->suffix(sprintf("%s.lock", $resourceIdentifier));
        $fp = fopen($lockFilePath, "c+");
        if (!$fp) {
            throw new ResourceLockException('Cannot get lock file pointer resource');
        }

        $concurrentSleep = $concurrentReqInterval && $concurrentReqInterval > 0 ?
            intval($concurrentReqInterval * 10 ^ 6) : null;

        $timer = time();
        while (true) {
            if (!flock($fp, LOCK_EX | LOCK_NB)) {
                if (!$concurrentSleep) {
                    throw new ConcurrentRequestBlocked('Concurrent request blocked');
                }

                usleep($concurrentSleep);
                if ($concurrentReqTimeout > 0) {
                    if ((time() - $timer) >= $concurrentReqTimeout) {
                        throw new ConcurrentRequestTimeout('Concurrent request timed out');
                    }
                }

                continue;
            }

            break;
        }

        $lastTimestamp = fread($fp, 15);
        if ($lastTimestamp) {
            $this->lastTimestamp = floatval($lastTimestamp);
        }

        ftruncate($fp, 0);
        fseek($fp, 0, SEEK_SET);
        fwrite($fp, strval(microtime(true)));
        $this->isLocked = true;
        $this->fp = $fp;
    }

    /**
     * @return void
     */
    public function setAutoRelease(): void
    {
        if ($this->autoReleaseSet) {
            return;

        }

        $resourceLock = $this;
        register_shutdown_function(function () use ($resourceLock) {
            $resourceLock->release();
        });

        $this->autoReleaseSet = true;
    }

    /**
     * @return float|null
     */
    public function lastTimestamp(): ?float
    {
        return $this->lastTimestamp;
    }

    /**
     * @param float $seconds
     * @return bool
     */
    public function checkElapsedTime(float $seconds): bool
    {
        if (!$this->lastTimestamp) {
            return true;
        }

        return ((microtime(true) - $this->lastTimestamp) >= $seconds);
    }

    /**
     * @return bool
     */
    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    /**
     * @throws ResourceLockException
     */
    public function release(): void
    {
        if (!$this->isLocked) {
            return;
        }

        $this->isLocked = false;
        $unlock = flock($this->fp, LOCK_UN);
        if (!$unlock) {
            throw new ResourceLockException('Could not unlock resource lock file');
        }

        fclose($this->fp);
    }
}