<?php

declare(strict_types=1);

namespace ProjectMovies\Buffer;

final class MemoryBufferStrategy implements BufferStrategyInterface
{
    public function createBuffer(): BufferInterface
    {
        return new MemoryBuffer();
    }
}
