# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-11-17

### Added

- `Registry` to register Flysystem v3 filesystems under custom stream
  protocols, with matching `pm_register()` / `pm_unregister()` /
  `pm_protocols()` / `pm_configuration()` helper functions.
- `StreamWrapper` implementing the PHP stream wrapper protocol: reads,
  writes, seeks, truncation, stat, directory iteration, mkdir/rmdir,
  unlink, rename, touch, and flock delegation.
- Write buffering strategies: `MemoryBuffer`, `TempFileBuffer`, and
  `OverflowBuffer` (memory up to a threshold, then a temp file).
- Path normalization: `StripProtocolPathNormalizer` (default) and
  `PassThruPathNormalizer`.
- Locking: `PermissiveLockRegistry` (default) and `FlockLockRegistry`.
- Visibility conversion between Flysystem visibility and Unix permission
  bits via `UnixVisibilityConverter`.
- PSR-3 logging hooks in `Configuration`.
- PHPUnit test suite, PHPStan (level 8), PHP-CS-Fixer (@PSR12), and a
  GitHub Actions CI matrix for PHP 8.1–8.5.

[0.1.0]: https://github.com/MahendraDharma/ProjectMovies/releases/tag/v0.1.0
