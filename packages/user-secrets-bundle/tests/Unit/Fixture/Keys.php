<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Fixture;

use Phil\UserSecretsBundle\Application\Encryption\Envelope;
use Phil\UserSecretsBundle\Domain\ValueObject\SealedSecret;
use Phil\UserSecretsBundle\Infrastructure\KeyManagement\KeyManagementCipher;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\SodiumKms;

/**
 * Master keys made of one repeated byte, known to the tests and to no one else: "k1" is made of "a", "k2" of "b".
 */
final class Keys
{
    private function __construct()
    {
    }

    /**
     * A master key in base64url without padding, as a `sodium://` DSN reads it.
     */
    public static function key(string $byte): string
    {
        return sodium_bin2base64(str_repeat($byte, 32), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    /**
     * The local libsodium KMS of the component, over master keys given by the byte they repeat.
     *
     * @param array<string, string> $keys
     */
    public static function kms(array $keys = ['k1' => 'a', 'k2' => 'b']): SodiumKms
    {
        return new SodiumKms(new InMemoryKeyLoader(array_map(static fn (string $byte): string => str_repeat($byte, 32), $keys)));
    }

    /**
     * An envelope that seals under `$current` through a KMS, the local one over the test keys by default.
     */
    public static function envelope(string $current = 'k1', ?DataKeyGeneratorInterface $kms = null): Envelope
    {
        return new Envelope(new KeyManagementCipher($kms ?? self::kms()), $current);
    }

    /**
     * Any plaintext sealed as the envelope sealed a credential in the earlier format — its byte first, no algorithm byte —, with
     * the additional data given.
     */
    public static function sealed(string $plaintext, string $aad, string $keyId = 'k1'): SealedSecret
    {
        return new SealedSecret($keyId, base64_encode("\2".new KeyManagementCipher(self::kms())->seal($keyId, $plaintext, $aad)));
    }
}
