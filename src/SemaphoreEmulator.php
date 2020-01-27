<?php
declare(strict_types=1);

namespace FurqanSiddiqui\SemaphoreEmulator;

use Comely\Filesystem\Directory;
use FurqanSiddiqui\SemaphoreEmulator\Exception\SemaphoreEmulatorException;

/**
 * Class SemaphoreEmulator
 * @package FurqanSiddiqui\SemaphoreEmulator
 */
class SemaphoreEmulator
{
    /** @var Directory */
    private $dir;

    /**
     * SemaphoreEmulator constructor.
     * @param Directory $lockFilesDirectory
     * @throws SemaphoreEmulatorException
     */
    public function __construct(Directory $lockFilesDirectory)
    {
        $this->dir = $lockFilesDirectory;
        if (!$this->dir->permissions()->write()) {
            throw new SemaphoreEmulatorException('Semaphore emulator directory is not writable');
        } elseif (!$this->dir->permissions()->read()) {
            throw new SemaphoreEmulatorException('Semaphore emulator directory is not readable');
        }
    }

    /**
     * @return Directory
     */
    public function dir(): Directory
    {
        return $this->dir;
    }

    /**
     * @param string $resourceIdentifier
     * @param float|null $concurrentReqInterval
     * @param int $concurrentReqTimeout
     * @return ResourceLock
     * @throws Exception\ConcurrentRequestBlocked
     * @throws Exception\ResourceLockException
     */
    public function obtainLock(string $resourceIdentifier, ?float $concurrentReqInterval = null, int $concurrentReqTimeout = 30): ResourceLock
    {
        return new ResourceLock($this, $resourceIdentifier, $concurrentReqInterval, $concurrentReqTimeout);
    }
}