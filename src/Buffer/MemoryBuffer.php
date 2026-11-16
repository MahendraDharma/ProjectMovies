<?php

declare(strict_types=1);

namespace ProjectMovies\Buffer;

/**
 * Buffers entirely in memory. Fast, but large writes grow the process
 * memory footprint without bound. Good default for small files.
 */
final class MemoryBuffer implements BufferInterface
{
    /** @var resource|null */
    private $stream = null;

    public function stream()
    {
        if ($this->stream === null) {
            $stream = fopen('php://memory', 'r+b');

            if ($stream === false) {
                throw new \RuntimeException('Unable to open the buffer stream.');
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
