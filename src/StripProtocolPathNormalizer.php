<?php

declare(strict_types=1);

namespace ProjectMovies;

/**
 * Strips the "protocol://" prefix and any leading slashes, producing the
 * relative path Flysystem adapters expect. This is the default normalizer.
 *
 * Example: "mem://reports/q1.csv" becomes "reports/q1.csv".
 */
final class StripProtocolPathNormalizer implements PathNormalizerInterface
{
    public function normalize(string $path): string
    {
        $position = strpos($path, '://');

        if ($position !== false) {
            $path = substr($path, $position + 3);
        }

        return ltrim($path, '/');
    }
}
