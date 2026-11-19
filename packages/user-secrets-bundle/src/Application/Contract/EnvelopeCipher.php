<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Application\Contract;

use Phil\UserSecretsBundle\Application\Exception\SealingFailed;
use Phil\UserSecretsBundle\Application\Exception\UnknownKey;
use Phil\UserSecretsBundle\Application\Exception\UnreadableSecret;
use Phil\UserSecretsBundle\Domain\Encryption\SealedAlgorithm;
use SensitiveParameter;

/**
 * Envelope encryption under a named master key: the bundle's port to Symfony's KeyManagement component.
 *
 * The additional data is authenticated with the value and must be given again to open it. The master key is named as
 * the KMS names it.
 */
interface EnvelopeCipher
{
    /**
     * The algorithm the cipher seals with, written in the header of every sealed value; a value of another algorithm
     * is not handed to it.
     */
    public function algorithm(): SealedAlgorithm;

    /**
     * @throws UnknownKey    when the KMS has no master key of that name
     * @throws SealingFailed when the KMS is unreachable or refuses
     */
    public function seal(string $keyId, #[SensitiveParameter] string $plaintext, string $aad): string;

    /**
     * @throws UnreadableSecret when the envelope is malformed, was altered, was sealed with other additional data or
     *                          under a master key the KMS does not hold, or the KMS is unreachable or refuses
     */
    public function open(string $keyId, string $envelope, string $aad): string;
}
