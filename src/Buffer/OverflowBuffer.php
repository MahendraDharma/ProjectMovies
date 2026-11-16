<?php

declare(strict_types=1);

namespace ProjectMovies\Buffer;

/**
 * Buffers in memory until a threshold is exceeded, then transparently
 * spills the data to a temporary file on disk. A middle ground between
 * MemoryBuffer and TempFileBuffer.
 */
final class OverflowBuffer implements BufferInterface
{
    private int $maxMemoryBytes;

    /** @var resource|null */
    private $stream = null;

    public function __construct(int $maxMemoryBytes = 2 * 1024 * 1024)
    {
        if ($maxMemoryBytes < 0) {
            throw new \InvalidArgumentException(sprintf(
                '$maxMemoryBytes must be >= 0, got %d',
                $maxMemoryBytes,
            ));
        }

        $this->maxMemoryBytes = $maxMemoryBytes;
    }

    public function stream()
    {
        if ($this->stream === null) {
            $stream = fopen(sprintf('php://temp/maxresource:%d', $this->maxMemoryBytes), 'r+b');

            if ($stream === false) {
                throw new \RuntimeException('Unable to open the overflow buffer stream.');
            }

            $this->stream = $stream;
        }

        return $this->stream;
    }

    public function size(): int
    {
        if ($this->stream === null) {
            return 0;
        }

        return fstat($this->stream)['size'] ?? 0;
    }

    public function close(): void
    {
        if ($this->stream !== null) {
            fclose($this->stream);
            $this->stream = null;
        }
    }
}
