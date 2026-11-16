<?php

declare(strict_types=1);

namespace ProjectMovies\Exception;

final class ProtocolNotRegisteredException extends \RuntimeException implements ProjectMoviesException
{
    public static function forProtocol(string $protocol): self
    {
        return new self(sprintf(
            'No filesystem is registered for protocol "%s://". Call ProjectMovies\Registry::register() first.',
            $protocol,
        ));
    }
}
