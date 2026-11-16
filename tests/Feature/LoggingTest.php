<?php

declare(strict_types=1);

namespace ProjectMovies\Tests\Feature;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use ProjectMovies\Configuration;
use ProjectMovies\Registry;
use ProjectMovies\Tests\Support\SpyLogger;

final class LoggingTest extends TestCase
{
    private SpyLogger $logger;

    protected function setUp(): void
    {
        Registry::reset();

        $this->logger = new SpyLogger();
        Registry::register('logged', new Filesystem(new InMemoryFilesystemAdapter()), new Configuration(
            logger: $this->logger,
        ));
    }

    protected function tearDown(): void
    {
        Registry::reset();
    }

    public function testRegistrationAndUnregistrationAreLogged(): void
    {
        self::assertContains('ProjectMovies: registered protocol logged.', $this->logger->messages());

        Registry::unregister('logged');

        self::assertContains('ProjectMovies: unregistered protocol logged.', $this->logger->messages());
    }

    public function testFlushIsLoggedAtDebugLevel(): void
    {
        file_put_contents('logged://note.txt', 'logged write');

        $flushMessages = array_filter(
            $this->logger->records,
            static fn (array $record): bool => str_contains($record['message'], 'flushed')
        );

        self::assertNotEmpty($flushMessages);

        $record = $flushMessages[0] ?? array_values($flushMessages)[0];
        self::assertSame('debug', $record['level']);
        self::assertSame(12, $record['context']['size'] ?? null);
        self::assertSame('note.txt', $record['context']['path'] ?? null);
    }

    public function testAdapterFailureIsLoggedAsError(): void
    {
        $broken = new class () extends InMemoryFilesystemAdapter {
            public function fileExists(string $path): bool
            {
                throw new \RuntimeException('adapter exploded');
            }
        };

        Registry::register('brokenlog', new Filesystem($broken), new Configuration(
            logger: $this->logger,
        ));

        @fopen('brokenlog://missing.txt', 'rb');

        $errors = array_filter(
            $this->logger->records,
            static fn (array $record): bool => $record['level'] === 'error'
        );

        self::assertNotEmpty($errors);
    }
}
