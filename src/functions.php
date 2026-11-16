<?php

declare(strict_types=1);

namespace ProjectMovies;

use League\Flysystem\FilesystemOperator;

if (!\function_exists('pm_register')) {
    /**
     * Registers a Flysystem filesystem under a protocol name, making
     * "protocol://..." paths usable with native PHP file functions.
     *
     * Example:
     *
     *     pm_register('mem', new Filesystem(new InMemoryFilesystemAdapter()));
     *     file_put_contents('mem://greeting.txt', 'Hello!');
     *
     * @throws Exception\ProtocolAlreadyRegisteredException when the protocol is taken
     */
    function pm_register(string $protocol, FilesystemOperator $filesystem, ?Configuration $config = null): void
    {
        Registry::register($protocol, $filesystem, $config);
    }

    /**
     * Removes a protocol registration.
     *
     * @throws Exception\ProtocolNotRegisteredException when the protocol is unknown
     */
    function pm_unregister(string $protocol): void
    {
        Registry::unregister($protocol);
    }

    /**
     * Lists every registered protocol name.
     *
     * @return list<string>
     */
    function pm_protocols(): array
    {
        return Registry::protocols();
    }

    /**
     * Returns the configuration in effect for a protocol.
     *
     * @throws Exception\ProtocolNotRegisteredException when the protocol is unknown
     */
    function pm_configuration(string $protocol): Configuration
    {
        return Registry::configuration($protocol);
    }
}
