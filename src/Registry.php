<?php

declare(strict_types=1);

namespace ProjectMovies;

use League\Flysystem\FilesystemOperator;
use ProjectMovies\Exception\ProtocolAlreadyRegisteredException;
use ProjectMovies\Exception\ProtocolNotRegisteredException;

/**
 * Maps protocol names to Flysystem filesystems and registers the
 * corresponding PHP stream wrappers.
 *
 * The registry is necessarily a static service locator: PHP instantiates
 * stream wrapper classes implicitly behind the scenes, so there is no way
 * to hand dependencies to those instances through a constructor. All
 * interaction with the locator lives here and in StreamWrapper; keep your
 * own code depending on the filesystem instances you register instead.
 */
final class Registry
{
    /** @var array<string, array{filesystem: FilesystemOperator, config: Configuration}> */
    private static array $entries = [];

    /**
     * Registers a filesystem under a protocol name, e.g. "mem", making
     * "mem://..." paths usable with native PHP file functions.
     *
     * @throws ProtocolAlreadyRegisteredException when the protocol is taken
     * @throws \InvalidArgumentException           when the protocol name is not a valid scheme
     */
    public static function register(string $protocol, FilesystemOperator $filesystem, ?Configuration $config = null): void
    {
        if (self::isRegistered($protocol)) {
            throw ProtocolAlreadyRegisteredException::forProtocol($protocol);
        }

        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.\-]*$/', $protocol) !== 1) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid protocol name.', $protocol));
        }

        if (!stream_wrapper_register($protocol, StreamWrapper::class)) {
            throw new \RuntimeException(sprintf('Unable to register stream wrapper for protocol "%s://".', $protocol));
        }

        $config ??= new Configuration();

        self::$entries[$protocol] = ['filesystem' => $filesystem, 'config' => $config];

        $config->logger?->info('ProjectMovies: registered protocol {protocol}.', ['protocol' => $protocol]);
    }

    /**
     * Removes a protocol registration. Handles already opened on the
     * protocol keep working; they resolved their dependencies at open time.
     *
     * @throws ProtocolNotRegisteredException when the protocol is unknown
     */
    public static function unregister(string $protocol): void
    {
        if (!self::isRegistered($protocol)) {
            throw ProtocolNotRegisteredException::forProtocol($protocol);
        }

        stream_wrapper_unregister($protocol);
        self::$entries[$protocol]['config']->logger?->info('ProjectMovies: unregistered protocol {protocol}.', ['protocol' => $protocol]);
        unset(self::$entries[$protocol]);
    }

    public static function isRegistered(string $protocol): bool
    {
        return isset(self::$entries[$protocol]);
    }

    /**
     * Lists every registered protocol name.
     *
     * @return list<string>
     */
    public static function protocols(): array
    {
        return array_keys(self::$entries);
    }

    /**
     * Returns the filesystem registered for a protocol.
     *
     * @throws ProtocolNotRegisteredException when the protocol is unknown
     */
    public static function filesystem(string $protocol): FilesystemOperator
    {
        return self::entry($protocol)['filesystem'];
    }

    /**
     * Returns the configuration registered for a protocol.
     *
     * @throws ProtocolNotRegisteredException when the protocol is unknown
     */
    public static function configuration(string $protocol): Configuration
    {
        return self::entry($protocol)['config'];
    }

    /**
     * Internal: the full entry for a protocol, used by StreamWrapper.
     *
     * @return array{filesystem: FilesystemOperator, config: Configuration}
     *
     * @throws ProtocolNotRegisteredException when the protocol is unknown
     */
    public static function entry(string $protocol): array
    {
        if (!isset(self::$entries[$protocol])) {
            throw ProtocolNotRegisteredException::forProtocol($protocol);
        }

        return self::$entries[$protocol];
    }

    /**
     * Internal: wipes every registration. Intended for the test suite.
     */
    public static function reset(): void
    {
        foreach (self::protocols() as $protocol) {
            stream_wrapper_unregister($protocol);
        }

        self::$entries = [];
    }
}
