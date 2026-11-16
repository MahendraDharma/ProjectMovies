<?php

declare(strict_types=1);

namespace ProjectMovies\Visibility;

/**
 * Converts between Flysystem visibility strings and Unix permission bits,
 * used when reporting stat() data and when creating files/directories.
 */
interface VisibilityConverterInterface
{
    /**
     * Permission bits for a file with the given (possibly null) Flysystem
     * visibility.
     */
    public function permissionsForFile(?string $visibility): int;

    /**
     * Permission bits for a directory with the given (possibly null)
     * Flysystem visibility.
     */
    public function permissionsForDirectory(?string $visibility): int;

    /**
     * Flysystem visibility implied by the given permission bits — used to
     * translate mkdir()/chmod() modes into adapter visibility settings.
     */
    public function visibilityForPermissions(int $permissions): string;
}
