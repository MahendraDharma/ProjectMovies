<?php

declare(strict_types=1);

namespace ProjectMovies\Lock;

/**
 * No-op registry: every operation succeeds and nothing is tracked. This is
 * the default, matching the permissive behaviour of most remote
 * filesystems. Use when you do not need cross-handle coordination.
 */
final class PermissiveLockRegistry implements LockRegistryInterface
{
    public function acquire(string $protocol, string $path, int $operation, int $owner): bool
    {
        return true;
    }

    public function release(string $protocol, string $path, int $owner): void
    {
    }
}
