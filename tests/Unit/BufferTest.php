<?php

declare(strict_types=1);

namespace ProjectMovies\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProjectMovies\Buffer\MemoryBuffer;
use ProjectMovies\Buffer\OverflowBuffer;
use ProjectMovies\Buffer\TempFileBuffer;

final class BufferTest extends TestCase
{
    public function testEmptyBufferHasZeroSize(): void
    {
        self::assertSame(0, (new MemoryBuffer())->size());
        self::assertSame(0, (new TempFileBuffer())->size());
        self::assertSame(0, (new OverflowBuffer(64))->size());
    }

    public function testWriteReadRoundtripInMemoryBuffer(): void
    {
        $buffer = new MemoryBuffer();
        $stream = $buffer->stream();

        fwrite($stream, 'Hello, ProjectMovies!');
        rewind($stream);

        self::assertSame('Hello, ProjectMovies!', stream_get_contents($stream));
        self::assertSame(21, $buffer->size());
    }

    public function testStreamMethodIsIdempotent(): void
    {
        $buffer = new MemoryBuffer();

        self::assertSame($buffer->stream(), $buffer->stream());
    }

    public function testOverflowBufferKeepsContentPastThreshold(): void
    {
        $buffer = new OverflowBuffer(8);
        $stream = $buffer->stream();
        $payload = str_repeat('abcdefgh', 10); // 80 bytes > 8-byte threshold

        fwrite($stream, $payload);
        rewind($stream);

        self::assertSame($payload, stream_get_contents($stream));
        self::assertSame(80, $buffer->size());
    }

    public function testTempFileBufferKeepsLargeContent(): void
    {
        $buffer = new TempFileBuffer();
        $stream = $buffer->stream();
        $payload = str_repeat('0123456789abcdef', 70000); // ~1.1 MB

        fwrite($stream, $payload);
        rewind($stream);

        self::assertSame($payload, stream_get_contents($stream));
        self::assertSame(strlen($payload), $buffer->size());
    }

    public function testCloseResetsSizeAndAllowsReuse(): void
    {
        $buffer = new MemoryBuffer();
        $stream = $buffer->stream();
        fwrite($stream, 'abc');
        $buffer->close();

        self::assertSame(0, $buffer->size());

        $fresh = $buffer->stream();
        fwrite($fresh, 'z');

        self::assertSame(1, $buffer->size());
    }

    public function testNegativeOverflowThresholdIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new OverflowBuffer(-1);
    }
}
