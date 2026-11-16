<?php

declare(strict_types=1);

namespace ProjectMovies\Buffer;

final class OverflowBufferStrategy implements BufferStrategyInterface
{
    private int $maxMemoryBytes;

    public function __construct(int $maxMemoryBytes = 2 * 1024 * 1024)
    {
        $this->maxMemoryBytes = $maxMemoryBytes;
    }

    public function createBuffer(): BufferInterface
    {
        return new OverflowBuffer($this->maxMemoryBytes);
    }
}
