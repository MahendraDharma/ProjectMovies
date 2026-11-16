<?php

declare(strict_types=1);

namespace ProjectMovies\Buffer;

/**
 * A random-access write buffer backing an open stream handle.
 *
 * Flysystem cannot append to an existing file, so data written through a
 * stream handle is buffered locally and flushed to the filesystem when the
 * handle is closed (or explicitly flushed). A buffer behaves like a
 * seekable, readable+writable temporary file.
 */
interface BufferInterface
{
    /**
     * Opens the backing store and returns a seekable stream resource
     * opened in "r+b" mode. Implementations must be idempotent: repeated
     * calls return the same resource.
     *
     * @return resource
     */
    public function stream();

    /**
     * Current size of the buffered data, in bytes.
     */
    public function size(): int;

    /**
     * Releases the backing store. Safe to call multiple times.
     */
    public function close(): void;
}
