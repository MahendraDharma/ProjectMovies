<?php

declare(strict_types=1);

namespace ProjectMovies\Tests\Unit;

use League\Flysystem\Visibility;
use PHPUnit\Framework\TestCase;
use ProjectMovies\Visibility\UnixVisibilityConverter;

final class VisibilityConverterTest extends TestCase
{
    private UnixVisibilityConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new UnixVisibilityConverter();
    }

    public function testDefaults(): void
    {
        self::assertSame(0644, $this->converter->permissionsForFile(null));
        self::assertSame(0755, $this->converter->permissionsForDirectory(null));
    }

    public function testPublicVisibilityOpensWorldReadBit(): void
    {
        self::assertSame(0644, $this->converter->permissionsForFile(Visibility::PUBLIC));
        self::assertSame(0755, $this->converter->permissionsForDirectory(Visibility::PUBLIC));
    }

    public function testPrivateVisibilityClearsWorldReadBit(): void
    {
        self::assertSame(0640, $this->converter->permissionsForFile(Visibility::PRIVATE));
        self::assertSame(0750, $this->converter->permissionsForDirectory(Visibility::PRIVATE));
    }

    public function testPermissionsMapBackToVisibility(): void
    {
        self::assertSame(Visibility::PUBLIC, $this->converter->visibilityForPermissions(0644));
        self::assertSame(Visibility::PUBLIC, $this->converter->visibilityForPermissions(0755));
        self::assertSame(Visibility::PRIVATE, $this->converter->visibilityForPermissions(0600));
        self::assertSame(Visibility::PRIVATE, $this->converter->visibilityForPermissions(0700));
    }
}
