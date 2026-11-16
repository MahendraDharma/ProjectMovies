<?php

declare(strict_types=1);

namespace ProjectMovies\Buffer;

/**
 * Buffers to a temporary file on disk, keeping memory usage flat. Use when
 * writing files that may be large relative to available memory.
 */
final class TempFileBuffer implements BufferInterface
{
    /** @var resource|null */
    private $stream = null;

    public function stream()
    {
        if ($this->stream === null) {
            $stream = fopen('php://temp/maxresource:0', 'r+b');

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
