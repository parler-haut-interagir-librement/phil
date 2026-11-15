<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Application\Contract;

use RuntimeException;
use SensitiveParameter;

/**
 * Seals the private keys the bundle keeps in its table, and opens them: the port to a key management service. The key
 * identifier is authenticated with the sealed key, so that a sealed key copied to another row does not open.
 */
interface KeySealer
{
    /**
     * @throws RuntimeException when the key management service refuses or cannot be reached
     */
    public function seal(string $keyId, #[SensitiveParameter] string $key): string;

    /**
     * @throws RuntimeException when the sealed key was altered, moved, or sealed under a master key the service lacks
     */
    public function open(string $keyId, string $sealed): string;
}
