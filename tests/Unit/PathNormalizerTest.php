<?php

declare(strict_types=1);

namespace ProjectMovies\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ProjectMovies\PassThruPathNormalizer;
use ProjectMovies\StripProtocolPathNormalizer;

final class PathNormalizerTest extends TestCase
{
    public static function providePaths(): \Generator
    {
        yield 'nested path' => ['mem://reports/2026/q1.csv', 'reports/2026/q1.csv'];
        yield 'root' => ['mem://', ''];
        yield 'single segment' => ['mem://hello.txt', 'hello.txt'];
        yield 'leading slashes stripped' => ['mem:///double/slash', 'double/slash'];
    }

    #[DataProvider('providePaths')]
    public function testStripProtocolNormalizer(string $input, string $expected): void
    {
        self::assertSame($expected, (new StripProtocolPathNormalizer())->normalize($input));
    }

    public function testStripProtocolNormalizerWithoutScheme(): void
    {
        self::assertSame('plain/path', (new StripProtocolPathNormalizer())->normalize('plain/path'));
    }

    public function testPassThruNormalizerReturnsPathUnchanged(): void
    {
        self::assertSame(
            'mem://reports/q1.csv',
            (new PassThruPathNormalizer())->normalize('mem://reports/q1.csv'),
        );
    }
}
