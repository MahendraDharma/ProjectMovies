<?php

declare(strict_types=1);

namespace ProjectMovies\Tests\Feature;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use ProjectMovies\Buffer\TempFileBufferStrategy;
use ProjectMovies\Configuration;
use ProjectMovies\Lock\InMemoryLockRegistry;
use ProjectMovies\Registry;

final class StreamWrapperTest extends TestCase
{
    private Filesystem $fs;

    protected function setUp(): void
    {
        Registry::reset();
        clearstatcache();

        $this->fs = new Filesystem(new InMemoryFilesystemAdapter());
        Registry::register('mem', $this->fs);
    }

    protected function tearDown(): void
    {
        Registry::reset();
        clearstatcache();
    }

    public function testPutAndGetContentsRoundtrip(): void
    {
        file_put_contents('mem://greeting.txt', 'Hello, ProjectMovies!');

        self::assertSame('Hello, ProjectMovies!', file_get_contents('mem://greeting.txt'));
        self::assertSame('Hello, ProjectMovies!', $this->fs->read('greeting.txt'));
    }

    public function testGetContentsOfMissingFileReturnsFalse(): void
    {
        self::assertFalse(@file_get_contents('mem://missing.txt'));
    }

    public function testOpenReadModeStreamsContent(): void
    {
        file_put_contents('mem://a.txt', '0123456789');

        $handle = fopen('mem://a.txt', 'rb');
        self::assertIsResource($handle);

        self::assertSame('01234', fread($handle, 5));
        self::assertSame(5, ftell($handle));
        self::assertSame('56789', fread($handle, 100));
        self::assertTrue(feof($handle));

        fclose($handle);
    }

    public function testOpenReadOfMissingFileFails(): void
    {
        $warnings = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;

            return true;
        });

        try {
            $handle = fopen('mem://nope.txt', 'rb');
        } finally {
            restore_error_handler();
        }

        self::assertFalse($handle);
        self::assertStringContainsString('No such file or directory', implode(' | ', $warnings));
    }

    public function testWriteModeCreatesAndTruncates(): void
    {
        file_put_contents('mem://t.txt', 'old content');

        $handle = fopen('mem://t.txt', 'wb');
        self::assertIsResource($handle);
        fwrite($handle, 'new');
        fclose($handle);

        self::assertSame('new', file_get_contents('mem://t.txt'));
    }

    public function testReadPlusModeAllowsReadModifyWrite(): void
    {
        file_put_contents('mem://rplus.txt', 'Hello');

        $handle = fopen('mem://rplus.txt', 'r+b');
        self::assertIsResource($handle);
        self::assertSame('Hello', fread($handle, 5));

        fseek($handle, 0);
        fwrite($handle, 'J');
        fclose($handle);

        self::assertSame('Jello', file_get_contents('mem://rplus.txt'));
    }

    public function testWritePlusModeCanReadBackWrites(): void
    {
        $handle = fopen('mem://wplus.txt', 'w+b');
        self::assertIsResource($handle);
        fwrite($handle, 'buffered');
        rewind($handle);

        self::assertSame('buffered', stream_get_contents($handle));
        fclose($handle);

        self::assertSame('buffered', file_get_contents('mem://wplus.txt'));
    }

    public function testAppendModeAddsToExistingContent(): void
    {
        file_put_contents('mem://log.txt', 'line 1' . PHP_EOL);

        $handle = fopen('mem://log.txt', 'ab');
        self::assertIsResource($handle);
        fwrite($handle, 'line 2' . PHP_EOL);
        fclose($handle);

        self::assertSame('line 1' . PHP_EOL . 'line 2' . PHP_EOL, file_get_contents('mem://log.txt'));
    }

    public function testAppendPlusModeReadsThenAppends(): void
    {
        file_put_contents('mem://ap.txt', 'ab');

        $handle = fopen('mem://ap.txt', 'a+b');
        self::assertIsResource($handle);
        self::assertSame('ab', fread($handle, 10));

        fseek($handle, 0, SEEK_END);
        fwrite($handle, 'c');
        fclose($handle);

        self::assertSame('abc', file_get_contents('mem://ap.txt'));
    }

    public function testFilePutContentsWithAppendFlag(): void
    {
        file_put_contents('mem://app.txt', 'one');
        file_put_contents('mem://app.txt', 'two', FILE_APPEND);
        file_put_contents('mem://app.txt', 'three', FILE_APPEND);

        self::assertSame('onetwothree', file_get_contents('mem://app.txt'));
    }

    public function testExclusiveModeRefusesExistingFile(): void
    {
        file_put_contents('mem://x.txt', 'here');

        self::assertFalse(@fopen('mem://x.txt', 'xb'));
    }

    public function testExclusiveModeCreatesNewFile(): void
    {
        $handle = fopen('mem://fresh.txt', 'xb');
        self::assertIsResource($handle);

        fwrite($handle, 'first');
        fclose($handle);

        self::assertSame('first', file_get_contents('mem://fresh.txt'));
    }

    public function testExclusiveModeCreatesEmptyFileEvenWithoutWrites(): void
    {
        $handle = fopen('mem://empty-x.txt', 'xb');
        self::assertIsResource($handle);
        fclose($handle);

        self::assertTrue(file_exists('mem://empty-x.txt'));
        self::assertSame('', file_get_contents('mem://empty-x.txt'));
    }

    public function testCreateModeWritesWithoutTruncating(): void
    {
        file_put_contents('mem://c.txt', 'xyz');

        $handle = fopen('mem://c.txt', 'cb');
        self::assertIsResource($handle);
        fwrite($handle, 'ab');
        fclose($handle);

        // 'c' does not truncate: 'ab' overwrites 'xy' at position 0, 'z' stays.
        self::assertSame('abz', file_get_contents('mem://c.txt'));
    }

    public function testSeekAndTellAcrossWhences(): void
    {
        file_put_contents('mem://seek.txt', '0123456789');

        $handle = fopen('mem://seek.txt', 'r+b');
        self::assertIsResource($handle);

        fseek($handle, 4);
        self::assertSame(4, ftell($handle));

        fseek($handle, 2, SEEK_CUR);
        self::assertSame(6, ftell($handle));

        fseek($handle, -3, SEEK_END);
        self::assertSame(7, ftell($handle));
        self::assertSame('789', fread($handle, 10));

        rewind($handle);
        self::assertSame(0, ftell($handle));

        fclose($handle);
    }

    public function testTruncatePersistsOnClose(): void
    {
        file_put_contents('mem://trunc.txt', 'abcdef');

        $handle = fopen('mem://trunc.txt', 'r+b');
        self::assertIsResource($handle);
        ftruncate($handle, 3);
        fclose($handle);

        self::assertSame('abc', file_get_contents('mem://trunc.txt'));
    }

    public function testExplicitFlushPersistsMidStream(): void
    {
        $handle = fopen('mem://flush.txt', 'wb');
        self::assertIsResource($handle);
        fwrite($handle, 'visible');

        self::assertTrue(fflush($handle));
        self::assertSame('visible', file_get_contents('mem://flush.txt'));

        fwrite($handle, '-now');
        fflush($handle);
        self::assertSame('visible-now', file_get_contents('mem://flush.txt'));

        fclose($handle);
    }

    public function testWriteOnlyModeRejectsReads(): void
    {
        $handle = fopen('mem://wo.txt', 'wb');
        self::assertIsResource($handle);
        fwrite($handle, 'data');

        self::assertFalse(@fread($handle, 4));

        fclose($handle);
    }

    public function testStatFamily(): void
    {
        file_put_contents('mem://stat.txt', '12345');
        mkdir('mem://folder');

        self::assertTrue(file_exists('mem://stat.txt'));
        self::assertTrue(is_file('mem://stat.txt'));
        self::assertFalse(is_dir('mem://stat.txt'));
        self::assertSame(5, filesize('mem://stat.txt'));
        self::assertGreaterThan(0, filemtime('mem://stat.txt'));

        self::assertTrue(is_dir('mem://folder'));
        self::assertFalse(is_file('mem://folder'));
        self::assertTrue(is_dir('mem://'));
        self::assertFalse(file_exists('mem://ghost.txt'));
    }

    public function testStatReturnsNativeShapedArray(): void
    {
        file_put_contents('mem://s.txt', 'abc');

        $stat = stat('mem://s.txt');

        self::assertIsArray($stat);
        self::assertArrayHasKey('mode', $stat);
        self::assertSame($stat[2], $stat['mode']);
        self::assertSame(3, $stat[7]);
        self::assertSame(3, $stat['size']);
        self::assertSame(0100000, $stat['mode'] & 0170000, 'stat mode must carry the regular-file type bits');
    }

    public function testFstatOnOpenHandle(): void
    {
        file_put_contents('mem://fs.txt', 'abcd');

        $handle = fopen('mem://fs.txt', 'rb');
        self::assertIsResource($handle);
        $stat = fstat($handle);
        fclose($handle);

        self::assertIsArray($stat);
        self::assertSame(4, $stat['size']);
    }

    public function testMkdirNonRecursiveRequiresParent(): void
    {
        self::assertFalse(@mkdir('mem://missing-parent/child'));

        self::assertTrue(mkdir('mem://plain'));
        self::assertTrue(is_dir('mem://plain'));
    }

    public function testMkdirRecursiveCreatesParents(): void
    {
        self::assertTrue(mkdir('mem://deep/nested/tree', 0777, true));
        self::assertTrue(is_dir('mem://deep/nested/tree'));
    }

    public function testMkdirOnExistingDirectoryFails(): void
    {
        mkdir('mem://once');

        self::assertFalse(@mkdir('mem://once'));
    }

    public function testRmdirRequiresEmptyDirectory(): void
    {
        mkdir('mem://dir');
        file_put_contents('mem://dir/file.txt', 'x');

        self::assertFalse(@rmdir('mem://dir'));

        unlink('mem://dir/file.txt');
        self::assertTrue(rmdir('mem://dir'));
        self::assertFalse(is_dir('mem://dir'));
    }

    public function testRmdirMissingDirectoryFails(): void
    {
        self::assertFalse(@rmdir('mem://ghost'));
    }

    public function testUnlinkRemovesFiles(): void
    {
        file_put_contents('mem://gone.txt', 'x');

        self::assertTrue(unlink('mem://gone.txt'));
        self::assertFalse(file_exists('mem://gone.txt'));
    }

    public function testUnlinkMissingFileFails(): void
    {
        self::assertFalse(@unlink('mem://ghost.txt'));
    }

    public function testRenameMovesFiles(): void
    {
        file_put_contents('mem://origin.txt', 'payload');

        self::assertTrue(rename('mem://origin.txt', 'mem://target.txt'));
        self::assertFalse(file_exists('mem://origin.txt'));
        self::assertSame('payload', file_get_contents('mem://target.txt'));
    }

    public function testRenameAcrossProtocolsIsRejected(): void
    {
        Registry::register('other', new Filesystem(new InMemoryFilesystemAdapter()));
        file_put_contents('mem://stay.txt', 'x');

        self::assertFalse(@rename('mem://stay.txt', 'other://moved.txt'));
        self::assertTrue(file_exists('mem://stay.txt'));
    }

    public function testScandirListsDotEntriesAndChildren(): void
    {
        mkdir('mem://list');
        file_put_contents('mem://list/b.txt', 'b');
        file_put_contents('mem://list/a.txt', 'a');
        mkdir('mem://list/sub');

        $entries = scandir('mem://list');

        self::assertIsArray($entries);
        self::assertContains('.', $entries);
        self::assertContains('..', $entries);
        self::assertContains('a.txt', $entries);
        self::assertContains('b.txt', $entries);
        self::assertContains('sub', $entries);
    }

    public function testOpendirIterationWithRewind(): void
    {
        mkdir('mem://iter');
        file_put_contents('mem://iter/one.txt', '1');
        file_put_contents('mem://iter/two.txt', '2');

        $dir = opendir('mem://iter');
        self::assertIsResource($dir);

        $firstPass = [];
        while (($entry = readdir($dir)) !== false) {
            $firstPass[] = $entry;
        }

        rewinddir($dir);

        $secondPass = [];
        while (($entry = readdir($dir)) !== false) {
            $secondPass[] = $entry;
        }

        closedir($dir);

        self::assertSame($firstPass, $secondPass);
        self::assertContains('one.txt', $firstPass);
        self::assertContains('two.txt', $firstPass);
    }

    public function testOpendirOnMissingDirectoryFails(): void
    {
        self::assertFalse(@opendir('mem://ghost-dir'));
    }

    public function testCopyFromWrapperToLocalDisk(): void
    {
        file_put_contents('mem://export.txt', 'exported');

        $local = tempnam(sys_get_temp_dir(), 'pm-test-');
        self::assertTrue(copy('mem://export.txt', $local));
        self::assertSame('exported', file_get_contents($local));

        unlink($local);
    }

    public function testCopyFromLocalDiskToWrapper(): void
    {
        $local = tempnam(sys_get_temp_dir(), 'pm-test-');
        file_put_contents($local, 'imported');

        self::assertTrue(copy($local, 'mem://import.txt'));
        self::assertSame('imported', file_get_contents('mem://import.txt'));

        unlink($local);
    }

    public function testCopyBetweenWrapperProtocols(): void
    {
        Registry::register('two', new Filesystem(new InMemoryFilesystemAdapter()));
        file_put_contents('mem://cross.txt', 'crossing');

        self::assertTrue(copy('mem://cross.txt', 'two://cross.txt'));
        self::assertSame('crossing', file_get_contents('two://cross.txt'));
    }

    public function testStreamCopyToStream(): void
    {
        file_put_contents('mem://src.bin', 'source data');

        $src = fopen('mem://src.bin', 'rb');
        self::assertIsResource($src);
        $dst = fopen('mem://dst.bin', 'wb');
        self::assertIsResource($dst);

        self::assertSame(11, stream_copy_to_stream($src, $dst));

        fclose($src);
        fclose($dst);

        self::assertSame('source data', file_get_contents('mem://dst.bin'));
    }

    public function testTouchCreatesEmptyFile(): void
    {
        touch('mem://touched.txt');

        self::assertTrue(file_exists('mem://touched.txt'));
        self::assertSame('', file_get_contents('mem://touched.txt'));
    }

    public function testChmodIsNotSupported(): void
    {
        file_put_contents('mem://perm.txt', 'x');

        self::assertFalse(@chmod('mem://perm.txt', 0777));
    }

    public function testLargeWriteRoundtrips(): void
    {
        $payload = str_repeat('ProjectMovies', 100000); // 1.3 MB

        $handle = fopen('mem://big.bin', 'wb');
        self::assertIsResource($handle);
        $written = 0;
        $chunkSize = 8192;

        while ($written < strlen($payload)) {
            $written += fwrite($handle, substr($payload, $written, $chunkSize));
        }

        fclose($handle);

        self::assertSame(strlen($payload), $written);
        self::assertSame($payload, file_get_contents('mem://big.bin'));
    }

    public function testTempFileBufferStrategyEndToEnd(): void
    {
        Registry::register('tmpfs', new Filesystem(new InMemoryFilesystemAdapter()), new Configuration(
            bufferStrategy: new TempFileBufferStrategy(),
        ));

        $payload = str_repeat('buffered-on-disk', 50000);

        $handle = fopen('tmpfs://big.txt', 'wb');
        self::assertIsResource($handle);
        fwrite($handle, $payload);
        fclose($handle);

        self::assertSame($payload, file_get_contents('tmpfs://big.txt'));
    }

    public function testUnregisteredProtocolIsInert(): void
    {
        self::assertFalse(@file_exists('nowhere://x'));
        self::assertFalse(@fopen('nowhere://x', 'rb'));
    }

    public function testUnregisterMakesProtocolInertAgain(): void
    {
        file_put_contents('mem://temp.txt', 'x');
        Registry::unregister('mem');

        self::assertFalse(@file_exists('mem://temp.txt'));

        Registry::register('mem', new Filesystem(new InMemoryFilesystemAdapter()));
        self::assertFalse(@file_exists('mem://temp.txt'), 'a fresh registration must not see old data');
    }

    public function testFlockDelegatesToLockRegistry(): void
    {
        $handle = fopen('mem://locked.txt', 'wb');
        self::assertIsResource($handle);

        self::assertTrue(flock($handle, LOCK_EX | LOCK_NB));
        self::assertTrue(flock($handle, LOCK_UN));

        fclose($handle);
    }

    public function testInMemoryLockRegistryCoordinatesHandles(): void
    {
        Registry::register('locked-fs', new Filesystem(new InMemoryFilesystemAdapter()), new Configuration(
            locks: new InMemoryLockRegistry(),
        ));

        $first = fopen('locked-fs://contended.txt', 'wb');
        self::assertIsResource($first);
        $second = fopen('locked-fs://contended.txt', 'wb');
        self::assertIsResource($second);

        self::assertTrue(flock($first, LOCK_EX | LOCK_NB));
        self::assertFalse(flock($second, LOCK_EX | LOCK_NB));

        fclose($first); // releases the lock

        self::assertTrue(flock($second, LOCK_EX | LOCK_NB));

        fclose($second);
    }

    public function testAdapterErrorsSurfaceAsFalse(): void
    {
        $broken = new class () extends InMemoryFilesystemAdapter {
            public function fileExists(string $path): bool
            {
                throw new \RuntimeException('adapter exploded');
            }
        };

        Registry::register('broken', new Filesystem($broken));

        self::assertFalse(@file_exists('broken://anything'));
        self::assertFalse(@fopen('broken://anything', 'rb'));
    }
}
