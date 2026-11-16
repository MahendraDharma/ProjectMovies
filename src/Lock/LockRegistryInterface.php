<?php

declare(strict_types=1);

namespace ProjectMovies\Lock;

/**
 * Advisory locking for stream paths, driven by flock()-style operations.
 *
 * Every acquisition carries an $owner token (the object ID of the calling
 * stream handle) so registries can tell handles apart.
 *
 * @see \flock() for the $operation bit semantics
 */
interface LockRegistryInterface
{
    /**
     * @param int $operation LOCK_SH | LOCK_EX | LOCK_UN, optionally
     *                       combined with LOCK_NB
     * @param int $owner     token identifying the calling stream handle
     *
     * @return bool true when the lock operation succeeded
     */
    public function acquire(string $protocol, string $path, int $operation, int $owner): bool;

    /**
     * Releases whatever the given owner holds on a path. Called when a
     * stream handle is closed or unlocks.
     */
    public function release(string $protocol, string $path, int $owner): void;
}
