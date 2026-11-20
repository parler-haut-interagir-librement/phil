<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Console;

use JsonException;
use Phil\IdentityProviderBundle\Application\Exception\Refused;

/**
 * The public key set a command reads from a file: a JSON object whose `keys` is a list of JSON Web Keys (RFC 7517,
 * section 5), at most MAX_BYTES; the keys themselves are checked by the client.
 */
final class KeySetFile
{
    public const int MAX_BYTES = 65_536;

    private function __construct()
    {
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws Refused `client.jwks_invalid` when the file cannot be read or holds no key set
     */
    public static function read(string $path): array
    {
        $json = is_file($path) && is_readable($path) && filesize($path) <= self::MAX_BYTES ? file_get_contents($path) : false;

        try {
            $set = false === $json ? null : json_decode($json, true, 8, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $set = null;
        }

        $keys = \is_array($set) ? $set['keys'] ?? null : null;

        if (!\is_array($keys) || !array_is_list($keys) || [] !== array_filter($keys, static fn (mixed $key): bool => !\is_array($key))) {
            throw Refused::because('client.jwks_invalid', $path);
        }

        /** @var list<array<string, mixed>> $keys each an object, checked above */
        return $keys;
    }
}
