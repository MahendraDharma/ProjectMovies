<?php

declare(strict_types=1);

namespace ProjectMovies\Exception;

final class ProtocolAlreadyRegisteredException extends \RuntimeException implements ProjectMoviesException
{
    public static function forProtocol(string $protocol): self
    {
        return new self(sprintf(
            'A filesystem is already registered for protocol "%s://". Unregister it first if you want to replace it.',
            $protocol,
        ));
    }
}
