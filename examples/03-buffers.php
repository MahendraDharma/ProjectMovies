<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use ProjectMovies\Buffer\MemoryBufferStrategy;
use ProjectMovies\Buffer\OverflowBufferStrategy;
use ProjectMovies\Buffer\TempFileBufferStrategy;
use ProjectMovies\Configuration;
use ProjectMovies\Registry;

$filesystem = new Filesystem(new InMemoryFilesystemAdapter());

// Pure in-memory buffering (default): fastest, uses RAM.
Registry::register('fast', $filesystem, new Configuration(
    bufferStrategy: new MemoryBufferStrategy(),
));

// Always spill to a temp file: flat memory use, good for large writes.
Registry::register('safe', $filesystem, new Configuration(
    bufferStrategy: new TempFileBufferStrategy(),
));

// Memory up to 64 KiB, then transparently spill to a temp file.
Registry::register('mixed', $filesystem, new Configuration(
    bufferStrategy: new OverflowBufferStrategy(64 * 1024),
));

foreach (['fast', 'safe', 'mixed'] as $protocol) {
    file_put_contents("$protocol://big.txt", str_repeat('x', 1024 * 1024));
    printf("%s://%s wrote %d bytes\n", ...[$protocol, 'big.txt', filesize("$protocol://big.txt")]);
}
