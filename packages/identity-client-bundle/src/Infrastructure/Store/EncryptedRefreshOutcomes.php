<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\Store;

use Override;
use Phil\IdentityClientBundle\Application\Contract\RefreshOutcomes;
use Phil\IdentityClientBundle\Application\Refresh\RefreshOutcome;
use Phil\IdentityClientBundle\Application\Refresh\RefreshStatus;
use Psr\Cache\CacheItemPoolInterface;
use SensitiveParameter;

/**
 * Keeps each outcome in a cache pool, encrypted with a key derived from the refresh token it answers.
 *
 * Only a request that still holds that refresh token can read the tokens that replaced it: the pool alone gives nothing
 * away, and there is no key to manage.
 */
final readonly class EncryptedRefreshOutcomes implements RefreshOutcomes
{
    private const int UNAVAILABLE_TTL = 5;

    public function __construct(
        private CacheItemPoolInterface $pool,
        private int $ttl,
    ) {
    }

    #[Override]
    public function find(#[SensitiveParameter] string $refreshToken): ?RefreshOutcome
    {
        [$key, $secret] = $this->derive($refreshToken);
        $sealed = $this->pool->getItem($key)->get();
        if (!\is_string($sealed) || \strlen($sealed) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $opened = sodium_crypto_secretbox_open(substr($sealed, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($sealed, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $secret);

        return false === $opened ? null : $this->decode($opened);
    }

    #[Override]
    public function publish(#[SensitiveParameter] string $refreshToken, RefreshOutcome $outcome): void
    {
        [$key, $secret] = $this->derive($refreshToken);
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $payload = json_encode(['status' => $outcome->status->value, 'attributes' => $outcome->attributes], \JSON_THROW_ON_ERROR);
        $ttl = RefreshStatus::Unavailable === $outcome->status ? min(self::UNAVAILABLE_TTL, $this->ttl) : $this->ttl;

        $this->pool->save($this->pool->getItem($key)->set($nonce.sodium_crypto_secretbox($payload, $nonce, $secret))->expiresAfter($ttl));
    }

    /**
     * @return array{string, string} the cache key and the encryption key, independent of each other
     */
    private function derive(#[SensitiveParameter] string $refreshToken): array
    {
        $material = hash_hkdf('sha256', $refreshToken, 64, 'phil_identity_client.refresh_outcome');

        return ['phil_identity_client.refresh.'.sodium_bin2hex(substr($material, 0, 32)), substr($material, 32)];
    }

    /**
     * The payload was authenticated by the decryption, and only this class writes it.
     */
    private function decode(string $payload): RefreshOutcome
    {
        /** @var array{status: string, attributes: array<string, mixed>} $data */
        $data = json_decode($payload, true, flags: \JSON_THROW_ON_ERROR);

        return new RefreshOutcome(RefreshStatus::from($data['status']), $data['attributes']);
    }
}
