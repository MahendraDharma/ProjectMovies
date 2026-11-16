<?php

declare(strict_types=1);

namespace ProjectMovies\Visibility;

use League\Flysystem\Visibility;

/**
 * Standard Unix-flavoured conversion: public files/directories carry the
 * configured defaults (0644 / 0755), private ones are restricted to owner
 * and group (0640 / 0750). Permissions without a world-read bit map back
 * to private visibility.
 */
final class UnixVisibilityConverter implements VisibilityConverterInterface
{
    private const DEFAULT_FILE = 0644;
    private const DEFAULT_DIR = 0755;

    public function __construct(
        private readonly int $defaultFilePermissions = self::DEFAULT_FILE,
        private readonly int $defaultDirectoryPermissions = self::DEFAULT_DIR,
    ) {
    }

    public function permissionsForFile(?string $visibility): int
    {
        return $this->permissionsFor($visibility, $this->defaultFilePermissions, 0o004);
    }

    public function permissionsForDirectory(?string $visibility): int
    {
        return $this->permissionsFor($visibility, $this->defaultDirectoryPermissions, 0o005);
    }

    public function visibilityForPermissions(int $permissions): string
    {
        return ($permissions & 0o004) !== 0
            ? Visibility::PUBLIC
            : Visibility::PRIVATE;
    }

    private function permissionsFor(?string $visibility, int $default, int $publicBits): int
    {
        if ($visibility === null) {
            return $default;
        }

        return $visibility === Visibility::PUBLIC
            ? $default | $publicBits
            : $default & ~0o007;
    }
}
