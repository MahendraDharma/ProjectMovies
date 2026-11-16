<?php

declare(strict_types=1);

namespace ProjectMovies\Lock;

/**
 * Coordinates handles within the current process using a small advisory
 * state machine per "protocol://path" key.
 *
 * Flysystem filesystems may be remote or in-memory, so there is often no
 * real file to flock(). This registry tracks holders in plain PHP instead:
 * an exclusive lock blocks every other owner, a shared lock blocks
 * exclusive ones only. Because PHP request handling is single threaded, a
 * contended "blocking" acquisition fails immediately rather than spin
 * forever — use LOCK_NB semantics and treat false as "try again later".
 */
final class InMemoryLockRegistry implements LockRegistryInterface
{
    /** @var array<string, array<int, int>> path key => owner => LOCK_SH or LOCK_EX */
    private array $holders = [];

    public function acquire(string $protocol, string $path, int $operation, int $owner): bool
    {
        $key = $protocol . '://' . $path;
        $flags = $operation & (LOCK_SH | LOCK_EX | LOCK_UN);

        if ($flags === LOCK_UN) {
            $this->release($protocol, $path, $owner);

            return true;
        }

        if ($flags === LOCK_EX) {
            return $this->acquireExclusive($key, $owner);
        }

        if ($flags === LOCK_SH) {
            return $this->acquireShared($key, $owner);
        }

        return false;
    }

    public function release(string $protocol, string $path, int $owner): void
    {
        unset($this->holders[$protocol . '://' . $path][$owner]);
    }

    private function acquireExclusive(string $key, int $owner): bool
    {
        // Re-acquiring or upgrading: our own hold never blocks us.
        unset($this->holders[$key][$owner]);

        foreach ($this->holders[$key] ?? [] as $mode) {
            if ($mode === LOCK_SH || $mode === LOCK_EX) {
                return false;
            }
        }

        $this->holders[$key][$owner] = LOCK_EX;

        return true;
    }

    private function acquireShared(string $key, int $owner): bool
    {
        foreach ($this->holders[$key] ?? [] as $other => $mode) {
            if ($mode === LOCK_EX && $other !== $owner) {
                return false;
            }
        }

        $this->holders[$key][$owner] = LOCK_SH;

        return true;
    }
}
