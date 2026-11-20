<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Fixture;

use LogicException;
use OpenSSLAsymmetricKey;

/**
 * A key pair on P-256, as GitHub signs its secret scanning alerts with, and the document GitHub publishes its public
 * keys in.
 */
final readonly class GitHubKeys
{
    public OpenSSLAsymmetricKey $private;

    public string $publicPem;

    public function __construct(
        public string $identifier = 'bcb53661c06b4728e59d897fb6165d5c9cda0fd9cdf9d09ead458168deb7518c',
    ) {
        $private = openssl_pkey_new(['private_key_type' => \OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $details = $private instanceof OpenSSLAsymmetricKey ? openssl_pkey_get_details($private) : false;

        if (!$private instanceof OpenSSLAsymmetricKey || !\is_array($details) || !\is_string($details['key'] ?? null)) {
            throw new LogicException('OpenSSL made no key.');
        }

        $this->private = $private;
        $this->publicPem = $details['key'];
    }

    /**
     * The signature of the body, in base64, as the header carries it.
     */
    public function sign(string $body): string
    {
        if (!openssl_sign($body, $signature, $this->private, \OPENSSL_ALGO_SHA256) || !\is_string($signature)) {
            throw new LogicException('OpenSSL signed nothing.');
        }

        return base64_encode($signature);
    }

    /**
     * @param list<self> $others
     */
    public function document(array $others = []): string
    {
        return json_encode(['public_keys' => array_map(static fn (self $keys): array => ['key_identifier' => $keys->identifier, 'key' => $keys->publicPem, 'is_current' => true], [$this, ...$others])], \JSON_THROW_ON_ERROR);
    }
}
