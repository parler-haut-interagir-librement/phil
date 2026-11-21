<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Jose;

use Closure;

/**
 * The private keys a source has loaded, so that a key is decrypted and loaded once per process, not once per
 * signature: a few at most, the oldest dropped first. A key never changes under its name — its key identifier, given
 * once —, so that what is kept stays true from one request to the next in a worker.
 */
final class LoadedKeys
{
    public const int MAX = 4;

    /** @var array<string, PrivateKey> */
    private array $keys = [];

    /**
     * @param Closure(): PrivateKey $load
     */
    public function get(string $name, Closure $load): PrivateKey
    {
        if (isset($this->keys[$name])) {
            return $this->keys[$name];
        }

        $this->keys[$name] = $load();

        if (\count($this->keys) > self::MAX) {
            array_shift($this->keys);
        }

        return $this->keys[$name];
    }

    public function count(): int
    {
        return \count($this->keys);
    }
}
