<?php

declare(strict_types=1);

namespace ProjectMovies;

/**
 * Converts a full stream path such as "mem://reports/2026/q1.csv" into the
 * path representation a Flysystem filesystem expects.
 */
interface PathNormalizerInterface
{
    /**
     * @param string $path the full path as passed by the PHP engine,
     *                     including the protocol prefix
     *
     * @return string the path handed to the underlying filesystem
     */
    public function normalize(string $path): string;
}
