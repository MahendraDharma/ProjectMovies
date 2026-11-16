# ProjectMovies

**Flysystem v3 + PHP stream wrappers — your own filesystem, anywhere in PHP.**

[![CI](https://github.com/MahendraDharma/ProjectMovies/actions/workflows/ci.yml/badge.svg)](https://github.com/MahendraDharma/ProjectMovies/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.1--8.5-777bb3.svg)](https://www.php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

ProjectMovies lets you register any [Flysystem](https://flysystem.thephpleague.com) filesystem under a custom
stream protocol, then use PHP's **native file functions** on `protocol://path` URIs:

```php
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use ProjectMovies\Registry;

Registry::register('mem', new Filesystem(new InMemoryFilesystemAdapter()));

file_put_contents('mem://greeting.txt', 'Hello!');        // plain PHP, in-memory storage
echo file_get_contents('mem://greeting.txt');             // "Hello!"
mkdir('mem://reports/2026', 0777, true);                  // recursive mkdir works too
$entries = scandir('mem://reports');                      // so does directory listing
```

Any library, framework, or legacy code that expects local files can now read and
write S3, FTP, ZIP, in-memory, or any other Flysystem adapter — no code changes
needed at the call sites.

> Inspired by the concept of [elazar/flystream](https://github.com/elazar/flystream)
> (MIT). ProjectMovies is an independent clean-room implementation you can build
> on. Written by [Mahendra Dharma](https://github.com/MahendraDharma).

## Installation

```bash
composer require mahendradharma/projectmovies
```

Bring your own Flysystem adapters, e.g.:

```bash
composer require league/flysystem-memory    # in-memory
composer require league/flysystem-ziparchive # ZIP archives
```

## How it works

1. `Registry::register($protocol, $filesystem)` stores a Flysystem
   `FilesystemOperator` and calls PHP's `stream_wrapper_register()`.
2. From then on, the PHP engine routes every native operation on
   `"$protocol://..."` paths to `ProjectMovies\StreamWrapper`.
3. **Reads** stream straight from the adapter (`$filesystem->readStream()`).
   **Writes** accumulate in a buffer — Flysystem has no append operation — and
   flush to the filesystem on `fclose()`/`fflush()`.

Because PHP instantiates stream wrapper classes implicitly, the registry is a
static service locator by necessity. Keep your own code depending on the
filesystem instances you register; the locator only exists for the engine's
benefit.

## Usage

### Registering and unregistering

```php
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use ProjectMovies\Registry;

Registry::register('mem', new Filesystem(new InMemoryFilesystemAdapter()));
Registry::register('two', new Filesystem(new InMemoryFilesystemAdapter()));

Registry::protocols();   // ['mem', 'two']
Registry::unregister('two');
```

Helper functions are also available:

```php
pm_register('mem', $filesystem);
pm_unregister('mem');
pm_protocols();
pm_configuration('mem');
```

### Supported native operations

| Category     | Functions                                                                                       |
|--------------|-------------------------------------------------------------------------------------------------|
| Read/write   | `fopen` (`r`, `r+`, `w`, `w+`, `a`, `a+`, `x`, `x+`, `c`, `c+`), `fread`, `fwrite`, `fclose`      |
| Positioning  | `fseek`, `ftell`, `rewind`, `feof`, `ftruncate`, `fflush`                                         |
| Metadata     | `file_exists`, `filesize`, `filemtime`, `stat`, `fstat`, `is_file`, `is_dir`, `touch`             |
| Directories  | `mkdir` (incl. recursive), `rmdir`, `scandir`, `opendir`/`readdir`/`rewinddir`/`closedir`          |
| Manipulation | `unlink`, `rename` (same protocol only), `copy`, `file_put_contents` (incl. `FILE_APPEND`)          |
| Locking      | `flock` (delegated to the configured `LockRegistryInterface`)                                     |

`copy()` works across protocols and against the local filesystem.
`rename()` is limited to a single protocol (the engine's atomic move cannot
span adapters). `chmod`/`chown`/`chgrp` and `stream_select()` are not supported.

### Configuration

Each protocol gets its own `Configuration`, immutable and built from defaults:

```php
use ProjectMovies\Configuration;
use ProjectMovies\Buffer\OverflowBufferStrategy;
use ProjectMovies\Lock\InMemoryLockRegistry;
use ProjectMovies\PassThruPathNormalizer;

$config = new Configuration(
    pathNormalizer: new StripProtocolPathNormalizer(), // scheme stripped, relative path
    bufferStrategy: new OverflowBufferStrategy(64 * 1024),
    locks: new InMemoryLockRegistry(),
    visibility: new UnixVisibilityConverter(),
    logger: $psr3Logger, // optional PSR-3 logging
);

Registry::register('mem', $filesystem, $config);
```

#### Buffer strategies

Because Flysystem cannot append, a writable handle buffers its data and
rewrites the destination on flush:

- `MemoryBufferStrategy` — buffer fully in RAM (default). Fastest; watch memory
  for large files.
- `TempFileBufferStrategy` — buffer in a temp file on disk. Flat memory use.
- `OverflowBufferStrategy($maxBytes)` — RAM up to a threshold, then spill to a
  temp file.

Read-modify-write modes (`r+`, `a`, `a+`, `c`, `c+`) preload the current
content into the buffer, so they rewrite the whole file on flush.

#### Path normalizers

- `StripProtocolPathNormalizer` (default): `mem://a/b.txt` → `a/b.txt`.
- `PassThruPathNormalizer`: passes the full URI through, for adapters that want
  to interpret it themselves.

#### Locking

- `PermissiveLockRegistry` (default): `flock()` calls always succeed.
- `InMemoryLockRegistry`: coordinates handles per `protocol://path` with a
  small advisory state machine — useful when multiple handles in one process
  contend for one path.

#### Visibility

`UnixVisibilityConverter` maps between Flysystem visibility and Unix
permission bits, powering `stat()`/`is_dir()` output and the visibility
applied to `mkdir()`.

### Using it with third-party code

```php
// Some legacy library insists on local paths:
$library->writeConfig('mem://config/settings.ini', $contents);

// Generate a ZIP-backed virtual tree and hand it to a parser:
Registry::register('zip', new Filesystem(new ZipArchiveAdapter($path)));
$parser->loadDirectory('zip://schema/');
```

## Trade-offs to know about

- Writes are buffered: nothing reaches the filesystem until `fclose()` or
  `fflush()`, and read-modify-write modes rewrite whole files. Prefer the
  direct Flysystem API for very large or hot files; use the wrapper where the
  native API is the point.
- `stat()` data depends on the adapter: not every adapter reports `lastModified`
  or `visibility`; missing values degrade gracefully.
- Cross-protocol `rename()` is rejected (use `copy()` + `unlink()`).
- `stream_select()` (`stream_cast`) is not supported.

## Development

```bash
composer install
composer test        # PHPUnit
composer analyse     # PHPStan (level 8)
composer cs          # PHP-CS-Fixer check
composer cs:fix      # PHP-CS-Fixer fix
```

CI runs the suite on PHP 8.1–8.5 via GitHub Actions.

## Mulai Cepat (Bahasa Indonesia)

```bash
composer require mahendradharma/projectmovies league/flysystem-memory
```

```php
ProjectMovies\Registry::register('mem',
    new League\Flysystem\Filesystem(new League\Flysystem\InMemory\InMemoryFilesystemAdapter()));

file_put_contents('mem://halo.txt', 'Halo dari ProjectMovies!');
echo file_get_contents('mem://halo.txt');
```

Konsepnya: daftarkan filesystem Flysystem ke protokol kustom, lalu semua
fungsi file bawaan PHP (`fopen`, `file_get_contents`, `mkdir`, `scandir`, …)
otomatis bekerja pada path `protokol://`. Tulis dilakukan lewat buffer dan
di-flush saat `fclose()`/`fflush()` karena Flysystem tidak mendukung append.

## License

[MIT](LICENSE) — © Mahendra Dharma
