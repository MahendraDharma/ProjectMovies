<?php

declare(strict_types=1);

namespace ProjectMovies\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProjectMovies\Lock\InMemoryLockRegistry;
use ProjectMovies\Lock\PermissiveLockRegistry;

final class LockRegistryTest extends TestCase
{
    public function testPermissiveRegistryAcquiresEverything(): void
    {
        $registry = new PermissiveLockRegistry();

        self::assertTrue($registry->acquire('mem', 'a.txt', LOCK_EX, 1));
        self::assertTrue($registry->acquire('mem', 'a.txt', LOCK_SH | LOCK_NB, 2));
        self::assertTrue($registry->acquire('mem', 'a.txt', LOCK_UN, 1));
        $registry->release('mem', 'a.txt', 1);
    }

    public function testExclusiveLockBlocksOtherOwners(): void
    {
        $registry = new InMemoryLockRegistry();

        self::assertTrue($registry->acquire('mem', 'locked.txt', LOCK_EX | LOCK_NB, 1));
        self::assertFalse($registry->acquire('mem', 'locked.txt', LOCK_EX | LOCK_NB, 2));
        self::assertFalse($registry->acquire('mem', 'locked.txt', LOCK_SH | LOCK_NB, 2));

        $registry->release('mem', 'locked.txt', 1);

        self::assertTrue($registry->acquire('mem', 'locked.txt', LOCK_EX | LOCK_NB, 2));
        $registry->release('mem', 'locked.txt', 2);
    }

    public function testSameOwnerCanReacquire(): void
    {
        $registry = new InMemoryLockRegistry();

        self::assertTrue($registry->acquire('mem', 'file.txt', LOCK_EX | LOCK_NB, 1));
        self::assertTrue($registry->acquire('mem', 'file.txt', LOCK_EX | LOCK_NB, 1));
        self::assertTrue($registry->acquire('mem', 'file.txt', LOCK_SH | LOCK_NB, 1));

        $registry->release('mem', 'file.txt', 1);

        self::assertTrue($registry->acquire('mem', 'file.txt', LOCK_EX | LOCK_NB, 2));
        $registry->release('mem', 'file.txt', 2);
    }

    public function testSharedLocksCoexistAcrossOwners(): void
    {
        $registry = new InMemoryLockRegistry();

        self::assertTrue($registry->acquire('mem', 'doc.txt', LOCK_SH | LOCK_NB, 1));
        self::assertTrue($registry->acquire('mem', 'doc.txt', LOCK_SH | LOCK_NB, 2));
        self::assertFalse($registry->acquire('mem', 'doc.txt', LOCK_EX | LOCK_NB, 3));

        $registry->release('mem', 'doc.txt', 1);
        $registry->release('mem', 'doc.txt', 2);

        self::assertTrue($registry->acquire('mem', 'doc.txt', LOCK_EX | LOCK_NB, 3));
        $registry->release('mem', 'doc.txt', 3);
    }

    public function testUnlockViaAcquire(): void
    {
        $registry = new InMemoryLockRegistry();

        $registry->acquire('mem', 'file.txt', LOCK_EX | LOCK_NB, 1);
        self::assertTrue($registry->acquire('mem', 'file.txt', LOCK_UN, 1));
        self::assertTrue($registry->acquire('mem', 'file.txt', LOCK_EX | LOCK_NB, 2));

        $registry->release('mem', 'file.txt', 2);
    }

    public function testLocksAreKeyedByPath(): void
    {
        $registry = new InMemoryLockRegistry();

        self::assertTrue($registry->acquire('mem', 'one.txt', LOCK_EX | LOCK_NB, 1));
        self::assertTrue($registry->acquire('mem', 'two.txt', LOCK_EX | LOCK_NB, 2));

        $registry->release('mem', 'one.txt', 1);
        $registry->release('mem', 'two.txt', 2);
    }
}
