<?php

declare(strict_types=1);

namespace ProjectMovies;

/**
 * Passes the full path through unchanged, protocol prefix included. Useful
 * with adapters that want to interpret the complete URI themselves.
 *
 * Example: "mem://reports/q1.csv" stays "mem://reports/q1.csv".
 */
final class PassThruPathNormalizer implements PathNormalizerInterface
{
    public function normalize(string $path): string
    {
        return $path;
    }
}
