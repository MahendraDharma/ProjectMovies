<?php

declare(strict_types=1);

namespace ProjectMovies\Buffer;

/**
 * Creates one buffer per opened write handle.
 */
interface BufferStrategyInterface
{
    public function createBuffer(): BufferInterface;
}
