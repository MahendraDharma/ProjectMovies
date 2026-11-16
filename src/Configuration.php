<?php

declare(strict_types=1);

namespace ProjectMovies;

use ProjectMovies\Buffer\BufferStrategyInterface;
use ProjectMovies\Buffer\MemoryBufferStrategy;
use ProjectMovies\Lock\LockRegistryInterface;
use ProjectMovies\Lock\PermissiveLockRegistry;
use ProjectMovies\Visibility\UnixVisibilityConverter;
use ProjectMovies\Visibility\VisibilityConverterInterface;
use Psr\Log\LoggerInterface;

/**
 * Immutable per-protocol settings applied to every stream handle opened
 * on a registered filesystem.
 */
final class Configuration
{
    public function __construct(
        public readonly PathNormalizerInterface $pathNormalizer = new StripProtocolPathNormalizer(),
        public readonly BufferStrategyInterface $bufferStrategy = new MemoryBufferStrategy(),
        public readonly LockRegistryInterface $locks = new PermissiveLockRegistry(),
        public readonly VisibilityConverterInterface $visibility = new UnixVisibilityConverter(),
        public readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Returns a copy of this configuration with the given logger attached.
     */
    public function withLogger(LoggerInterface $logger): self
    {
        return new self(
            $this->pathNormalizer,
            $this->bufferStrategy,
            $this->locks,
            $this->visibility,
            $logger,
        );
    }
}
