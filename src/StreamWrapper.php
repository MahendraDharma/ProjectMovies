<?php

declare(strict_types=1);

namespace ProjectMovies;

use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;

/**
 * The PHP stream wrapper that bridges native file functions to a
 * registered Flysystem filesystem.
 *
 * Reads pass straight through to the adapter's readStream(). Writes are
 * accumulated in a Buffer (Flysystem has no append operation) and flushed
 * to the filesystem when the handle is closed or explicitly flushed.
 *
 * @see https://www.php.net/manual/en/class.streamwrapper.php
 */
final class StreamWrapper
{
    /**
     * Stream context resource assigned by the engine when fopen() receives
     * one. Not interpreted by this wrapper; declared for engine visibility.
     *
     * @var resource|null
     */
    public $context;

    private const MODE_PATTERN = '/^([rwxac])(\+?)$/';

    private ?FilesystemOperator $filesystem = null;
    private ?Configuration $config = null;
    private string $protocol = '';
    private string $path = '';

    private string $mode = '';
    private bool $readable = false;
    private bool $writable = false;
    private bool $appending = false;
    private bool $dirty = false;

    /** @var resource|null */
    private $readStream = null;

    private ?Buffer\BufferInterface $buffer = null;

    /** @var list<string> */
    private array $dirEntries = [];
    private int $dirKey = 0;

    /**
     * Resolves a full "protocol://path" URI against the registry.
     *
     * @return array{protocol: string, path: string, filesystem: FilesystemOperator, config: Configuration}|null
     */
    private static function resolve(string $uri): ?array
    {
        $position = strpos($uri, '://');

        if ($position === false) {
            return null;
        }

        $protocol = substr($uri, 0, $position);

        try {
            $entry = Registry::entry($protocol);
        } catch (Exception\ProjectMoviesException) {
            return null;
        }

        return [
            'protocol' => $protocol,
            'path' => $entry['config']->pathNormalizer->normalize($uri),
            'filesystem' => $entry['filesystem'],
            'config' => $entry['config'],
        ];
    }

    private function logger(): ?LoggerInterface
    {
        return $this->config?->logger;
    }

    /**
     * Emits a warning carrying the real reason for a failure, alongside the
     * engine's generic "failed to open stream" message. Suppressed by the @
     * operator like any native PHP warning.
     */
    private function warn(string $message): void
    {
        trigger_error($message, E_USER_WARNING);
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $resolved = self::resolve($path);

        if ($resolved === null) {
            $this->warn(sprintf('fopen(%s): failed to open stream: protocol is not registered', $path));

            return false;
        }

        $cleanMode = str_replace(['b', 't'], '', $mode);

        if (preg_match(self::MODE_PATTERN, $cleanMode, $m) !== 1) {
            $this->warn(sprintf('fopen(%s): illegal mode "%s"', $path, $mode));

            return false;
        }

        $filesystem = $resolved['filesystem'];
        $config = $resolved['config'];

        $this->protocol = $resolved['protocol'];
        $this->path = $resolved['path'];
        $this->filesystem = $filesystem;
        $this->config = $config;
        $this->mode = $m[1] . $m[2];
        $this->appending = $m[1] === 'a';
        $plus = $m[2] === '+';

        try {
            $exists = $filesystem->fileExists($this->path);
        } catch (\Throwable $e) {
            $this->logger()?->error('ProjectMovies: existence check failed for {path}: {error}', ['path' => $this->path, 'error' => $e->getMessage()]);
            $this->warn(sprintf('fopen(%s): %s', $path, $e->getMessage()));

            return false;
        }

        switch ($m[1]) {
            case 'r':
                if (!$exists) {
                    $this->warn(sprintf('fopen(%s): failed to open stream: No such file or directory', $path));

                    return false;
                }

                $this->readable = true;

                if ($plus) {
                    $this->writable = true;

                    if (!$this->loadExisting()) {
                        return false;
                    }
                }

                break;

            case 'w':
                $this->writable = true;
                $this->readable = $plus;
                $this->dirty = true; // truncates or creates on flush

                break;

            case 'x':
                if ($exists) {
                    $this->warn(sprintf('fopen(%s): failed to open stream: File exists', $path));

                    return false;
                }

                $this->writable = true;
                $this->readable = $plus;
                $this->dirty = true;

                break;

            case 'a':
            case 'c':
                $this->writable = true;
                $this->readable = $plus;
                $this->dirty = !$exists;

                if ($exists && !$this->loadExisting()) {
                    return false;
                }

                break;
        }

        if ($this->writable && $this->buffer === null) {
            $this->buffer = $config->bufferStrategy->createBuffer();
            $this->buffer->stream();
        }

        if ($this->mode === 'r') {
            try {
                $this->readStream = $filesystem->readStream($this->path);
            } catch (\Throwable $e) {
                $this->logger()?->error('ProjectMovies: read open failed for {path}: {error}', ['path' => $this->path, 'error' => $e->getMessage()]);
                $this->warn(sprintf('fopen(%s): failed to open stream: %s', $path, $e->getMessage()));

                return false;
            }
        }

        if ($this->readStream !== null) {
            fseek($this->readStream, 0);
        } elseif ($this->buffer !== null) {
            fseek($this->buffer->stream(), 0);
        }

        $openedPath = $path;

        return true;
    }

    /**
     * Copies the current file content into the write buffer. Used by the
     * read-modify-write modes (r+, a, a+, c, c+) because Flysystem cannot
     * append — the whole file is rewritten from the buffer on flush.
     */
    private function loadExisting(): bool
    {
        assert($this->config !== null && $this->filesystem !== null);

        $buffer = $this->config->bufferStrategy->createBuffer();
        $buffer->stream();

        try {
            $source = $this->filesystem->readStream($this->path);
            stream_copy_to_stream($source, $buffer->stream());
            fclose($source);
        } catch (\Throwable $e) {
            $this->logger()?->error('ProjectMovies: could not preload {path}: {error}', ['path' => $this->path, 'error' => $e->getMessage()]);
            $this->warn(sprintf('fopen(%s): failed to open stream: %s', $this->protocol . '://' . $this->path, $e->getMessage()));

            return false;
        }

        $this->buffer = $buffer;

        return true;
    }

    public function stream_read(int $count): string|false
    {
        if ($count < 1) {
            return '';
        }

        if ($this->readable && $this->buffer !== null) {
            return fread($this->buffer->stream(), $count);
        }

        if ($this->readStream !== null) {
            return fread($this->readStream, $count);
        }

        return false;
    }

    public function stream_write(string $data): int
    {
        if (!$this->writable || $this->buffer === null) {
            return 0;
        }

        $stream = $this->buffer->stream();

        if ($this->appending) {
            fseek($stream, 0, SEEK_END);
        }

        $written = 0;
        $length = strlen($data);

        while ($written < $length) {
            $chunk = fwrite($stream, substr($data, $written));

            if ($chunk === false) {
                break;
            }

            $written += $chunk;
        }

        if ($written > 0) {
            $this->dirty = true;
        }

        return $written;
    }

    public function stream_eof(): bool
    {
        if ($this->buffer !== null) {
            return feof($this->buffer->stream());
        }

        return $this->readStream === null || feof($this->readStream);
    }

    public function stream_tell(): int
    {
        if ($this->buffer !== null) {
            return (int) ftell($this->buffer->stream());
        }

        return $this->readStream === null ? 0 : (int) ftell($this->readStream);
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        $stream = $this->buffer !== null ? $this->buffer->stream() : $this->readStream;

        if ($stream === null) {
            return false;
        }

        return fseek($stream, $offset, $whence) === 0;
    }

    public function stream_truncate(int $newSize): bool
    {
        if ($newSize < 0 || !$this->writable || $this->buffer === null) {
            return false;
        }

        if (!ftruncate($this->buffer->stream(), $newSize)) {
            return false;
        }

        $this->dirty = true;

        return true;
    }

    /**
     * Persists the write buffer to the filesystem as a whole-file write.
     */
    private function persist(): void
    {
        assert($this->buffer !== null && $this->filesystem !== null);

        $stream = $this->buffer->stream();
        $position = ftell($stream);
        rewind($stream);

        $this->filesystem->writeStream($this->path, $stream);

        fseek($stream, (int) $position);

        $this->logger()?->debug('ProjectMovies: flushed {size} bytes to {path}.', ['size' => $this->buffer->size(), 'path' => $this->path]);
    }

    public function stream_flush(): bool
    {
        if (!$this->writable || !$this->dirty || $this->buffer === null) {
            return true;
        }

        try {
            $this->persist();
            $this->dirty = false;

            return true;
        } catch (\Throwable $e) {
            $this->logger()?->error('ProjectMovies: flush failed for {path}: {error}', ['path' => $this->path, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function stream_close(): void
    {
        if ($this->writable && $this->dirty && $this->buffer !== null) {
            try {
                $this->persist();
            } catch (\Throwable $e) {
                $this->logger()?->error('ProjectMovies: final flush failed for {path}: {error}', ['path' => $this->path, 'error' => $e->getMessage()]);
            }
        }

        if ($this->config !== null) {
            $this->config->locks->release($this->protocol, $this->path, spl_object_id($this));
        }

        if ($this->buffer !== null) {
            $this->buffer->close();
            $this->buffer = null;
        }

        if ($this->readStream !== null) {
            fclose($this->readStream);
            $this->readStream = null;
        }
    }

    /**
     * Builds a stat() array for a path, or false when it does not exist.
     *
     * @return array<int|string, int>|false
     */
    public function url_stat(string $path, int $flags): array|false
    {
        $resolved = self::resolve($path);

        if ($resolved === null) {
            return false;
        }

        try {
            if ($resolved['path'] === '') {
                return self::statArray(040000 | $resolved['config']->visibility->permissionsForDirectory(null), 0, 0);
            }

            if ($resolved['filesystem']->fileExists($resolved['path'])) {
                $size = $resolved['filesystem']->fileSize($resolved['path']);
                $mtime = $resolved['filesystem']->lastModified($resolved['path']);
                $visibility = self::visibilityOf($resolved['filesystem'], $resolved['path']);
                $mode = 0100000 | $resolved['config']->visibility->permissionsForFile($visibility);

                return self::statArray($mode, $size, $mtime);
            }

            if ($resolved['filesystem']->directoryExists($resolved['path'])) {
                $mode = 040000 | $resolved['config']->visibility->permissionsForDirectory(
                    self::directoryVisibilityOf($resolved['filesystem'], $resolved['path']),
                );

                return self::statArray($mode, 0, 0);
            }

            return false;
        } catch (\Throwable $e) {
            $resolved['config']->logger?->debug('ProjectMovies: stat failed for {path}: {error}', ['path' => $resolved['path'], 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @return array<int|string, int>|false
     */
    public function stream_stat(): array|false
    {
        if ($this->filesystem === null) {
            return false;
        }

        return $this->url_stat($this->protocol . '://' . $this->path, 0);
    }

    /**
     * @return array<int|string, int>
     */
    private static function statArray(int $mode, int $size, int $mtime): array
    {
        return [
            0 => 0, 1 => 0, 2 => $mode, 3 => 0, 4 => 0, 5 => 0, 6 => 0,
            7 => $size, 8 => $mtime, 9 => $mtime, 10 => $mtime, 11 => -1, 12 => -1,
            'dev' => 0, 'ino' => 0, 'mode' => $mode, 'nlink' => 0, 'uid' => 0,
            'gid' => 0, 'rdev' => 0, 'size' => $size, 'atime' => $mtime,
            'mtime' => $mtime, 'ctime' => $mtime, 'blksize' => -1, 'blocks' => -1,
        ];
    }

    private static function visibilityOf(FilesystemOperator $fs, string $path): ?string
    {
        try {
            return $fs->visibility($path);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function directoryVisibilityOf(FilesystemOperator $fs, string $path): ?string
    {
        try {
            foreach ($fs->listContents(dirname($path), FilesystemOperator::LIST_SHALLOW) as $attributes) {
                if ($attributes instanceof FileAttributes || $attributes->path() !== $path) {
                    continue;
                }

                return $attributes->visibility();
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        $resolved = self::resolve($path);

        if ($resolved === null) {
            return false;
        }

        try {
            if ($resolved['filesystem']->directoryExists($resolved['path'])) {
                $this->warn(sprintf('mkdir(): Directory exists: %s', $path));

                return false;
            }

            if (($options & STREAM_MKDIR_RECURSIVE) === 0) {
                $parent = dirname($resolved['path']);

                if ($parent !== '.' && $parent !== '/' && $parent !== '' && !$resolved['filesystem']->directoryExists($parent)) {
                    $this->warn(sprintf('mkdir(): No such file or directory: %s', $path));

                    return false;
                }
            }

            $resolved['filesystem']->createDirectory($resolved['path'], [
                'directory_visibility' => $resolved['config']->visibility->visibilityForPermissions($mode),
            ]);

            return true;
        } catch (\Throwable $e) {
            $resolved['config']->logger?->error('ProjectMovies: mkdir failed for {path}: {error}', ['path' => $resolved['path'], 'error' => $e->getMessage()]);
            $this->warn(sprintf('mkdir(): %s', $e->getMessage()));

            return false;
        }
    }

    public function rmdir(string $path): bool
    {
        $resolved = self::resolve($path);

        if ($resolved === null) {
            return false;
        }

        try {
            if (!$resolved['filesystem']->directoryExists($resolved['path'])) {
                $this->warn(sprintf('rmdir(%s): No such file or directory', $path));

                return false;
            }

            $contents = $resolved['filesystem']->listContents($resolved['path'], FilesystemOperator::LIST_SHALLOW)->toArray();

            if ($contents !== []) {
                $this->warn(sprintf('rmdir(%s): Directory not empty', $path));

                return false;
            }

            $resolved['filesystem']->deleteDirectory($resolved['path']);

            return true;
        } catch (\Throwable $e) {
            $resolved['config']->logger?->error('ProjectMovies: rmdir failed for {path}: {error}', ['path' => $resolved['path'], 'error' => $e->getMessage()]);
            $this->warn(sprintf('rmdir(): %s', $e->getMessage()));

            return false;
        }
    }

    public function unlink(string $path): bool
    {
        $resolved = self::resolve($path);

        if ($resolved === null) {
            return false;
        }

        try {
            if ($resolved['filesystem']->fileExists($resolved['path'])) {
                $resolved['filesystem']->delete($resolved['path']);

                return true;
            }

            if ($resolved['filesystem']->directoryExists($resolved['path'])) {
                $this->warn(sprintf('unlink(%s): Is a directory', $path));
            } else {
                $this->warn(sprintf('unlink(%s): No such file or directory', $path));
            }

            return false;
        } catch (\Throwable $e) {
            $resolved['config']->logger?->error('ProjectMovies: unlink failed for {path}: {error}', ['path' => $resolved['path'], 'error' => $e->getMessage()]);
            $this->warn(sprintf('unlink(): %s', $e->getMessage()));

            return false;
        }
    }

    public function rename(string $from, string $to): bool
    {
        $source = self::resolve($from);
        $target = self::resolve($to);

        if ($source === null || $target === null) {
            $this->warn(sprintf('rename(%s,%s): No such file or directory', $from, $to));

            return false;
        }

        if ($source['protocol'] !== $target['protocol']) {
            $this->warn(sprintf('rename(%s,%s): Cross-protocol rename is not supported by ProjectMovies', $from, $to));

            return false;
        }

        try {
            $source['filesystem']->move($source['path'], $target['path']);

            return true;
        } catch (\Throwable $e) {
            $source['config']->logger?->error('ProjectMovies: rename failed for {from} -> {to}: {error}', ['from' => $source['path'], 'to' => $target['path'], 'error' => $e->getMessage()]);
            $this->warn(sprintf('rename(): %s', $e->getMessage()));

            return false;
        }
    }

    public function dir_opendir(string $path, int $options): bool
    {
        $resolved = self::resolve($path);

        if ($resolved === null) {
            $this->warn(sprintf('opendir(%s): failed to open dir: protocol is not registered', $path));

            return false;
        }

        try {
            if ($resolved['filesystem']->fileExists($resolved['path'])) {
                $this->warn(sprintf('opendir(%s): failed to open dir: Not a directory', $path));

                return false;
            }

            if ($resolved['path'] !== '' && !$resolved['filesystem']->directoryExists($resolved['path'])) {
                $this->warn(sprintf('opendir(%s): failed to open dir: No such file or directory', $path));

                return false;
            }

            $names = ['.', '..'];

            foreach ($resolved['filesystem']->listContents($resolved['path'], FilesystemOperator::LIST_SHALLOW) as $attributes) {
                $names[] = basename($attributes->path());
            }

            $this->dirEntries = $names;
            $this->dirKey = 0;

            return true;
        } catch (\Throwable $e) {
            $resolved['config']->logger?->error('ProjectMovies: opendir failed for {path}: {error}', ['path' => $resolved['path'], 'error' => $e->getMessage()]);
            $this->warn(sprintf('opendir(%s): failed to open dir: %s', $path, $e->getMessage()));

            return false;
        }
    }

    public function dir_readdir(): string|false
    {
        $entry = $this->dirEntries[$this->dirKey] ?? false;

        if ($entry !== false) {
            $this->dirKey++;
        }

        return $entry;
    }

    public function dir_rewinddir(): bool
    {
        $this->dirKey = 0;

        return true;
    }

    public function dir_closedir(): bool
    {
        $this->dirEntries = [];
        $this->dirKey = 0;

        return true;
    }

    public function stream_lock(int $operation): bool
    {
        if ($this->config === null) {
            return false;
        }

        return $this->config->locks->acquire($this->protocol, $this->path, $operation, spl_object_id($this));
    }

    public function stream_metadata(string $path, int $option, mixed $value): bool
    {
        $resolved = self::resolve($path);

        if ($resolved === null) {
            return false;
        }

        if ($option === STREAM_META_TOUCH) {
            try {
                if (!$resolved['filesystem']->fileExists($resolved['path'])) {
                    $resolved['filesystem']->write($resolved['path'], '');
                }

                return true;
            } catch (\Throwable $e) {
                $resolved['config']->logger?->error('ProjectMovies: touch failed for {path}: {error}', ['path' => $resolved['path'], 'error' => $e->getMessage()]);

                return false;
            }
        }

        return false; // chmod/chown/chgrp are not representable on most adapters
    }

    /**
     * stream_select() is not supported.
     *
     * @return false
     */
    public function stream_cast(int $castAs)
    {
        return false;
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return true; // accept blocking/buffering options as no-ops
    }
}
