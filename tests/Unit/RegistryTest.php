<?php

declare(strict_types=1);

namespace ProjectMovies\Tests\Unit;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use ProjectMovies\Configuration;
use ProjectMovies\Exception\ProtocolAlreadyRegisteredException;
use ProjectMovies\Exception\ProtocolNotRegisteredException;
use ProjectMovies\Registry;

final class RegistryTest extends TestCase
{
    protected function setUp(): void
    {
        Registry::reset();
    }

    protected function tearDown(): void
    {
        Registry::reset();
    }

    public function testRegisterMakesProtocolAvailable(): void
    {
        Registry::register('mem', new Filesystem(new InMemoryFilesystemAdapter()));

        self::assertTrue(Registry::isRegistered('mem'));
        self::assertSame(['mem'], Registry::protocols());
    }

    public function testRegisterRejectsDuplicateProtocol(): void
    {
        Registry::register('mem', new Filesystem(new InMemoryFilesystemAdapter()));

        $this->expectException(ProtocolAlreadyRegisteredException::class);

        Registry::register('mem', new Filesystem(new InMemoryFilesystemAdapter()));
    }

    public function testRegisterRejectsInvalidProtocolName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Registry::register('not a protocol', new Filesystem(new InMemoryFilesystemAdapter()));
    }

    public function testUnregisterRemovesProtocol(): void
    {
        Registry::register('mem', new Filesystem(new InMemoryFilesystemAdapter()));
        Registry::unregister('mem');

        self::assertFalse(Registry::isRegistered('mem'));
        self::assertSame([], Registry::protocols());
    }

    public function testUnregisterUnknownProtocolThrows(): void
    {
        $this->expectException(ProtocolNotRegisteredException::class);

        Registry::unregister('ghost');
    }

    public function testFilesystemAccessorReturnsRegisteredInstance(): void
    {
        $fs = new Filesystem(new InMemoryFilesystemAdapter());
        Registry::register('mem', $fs);

        self::assertSame($fs, Registry::filesystem('mem'));
    }

    public function testConfigurationDefaultsAreProvided(): void
    {
        Registry::register('mem', new Filesystem(new InMemoryFilesystemAdapter()));

        self::assertInstanceOf(Configuration::class, Registry::configuration('mem'));
    }

    public function testEntryForUnknownProtocolThrows(): void
    {
        $this->expectException(ProtocolNotRegisteredException::class);

        Registry::entry('ghost');
    }
}
