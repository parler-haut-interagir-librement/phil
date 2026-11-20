<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Oidc;

use Closure;
use DateTimeImmutable;
use Jose\Component\Core\JWK;
use Override;
use Phil\IdentityProviderBundle\Application\Contract\SecretCipher;
use Phil\IdentityProviderBundle\Application\Contract\SigningKeySource;
use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Jose\LoadedKeys;
use Phil\IdentityProviderBundle\Application\Jose\PrivateKey;
use Phil\IdentityProviderBundle\Application\Journal;
use Phil\IdentityProviderBundle\Domain\Contract\SigningKeyRepository;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\SigningKey;
use Psr\Clock\ClockInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * The keys of the provider kept in its database, sealed by the key management service when `secrets.kms_key` is set,
 * and their rotation: the source of keys unless `oidc.signing_keys.source` names another.
 *
 * `rotate()`, run by `phil:identity-provider:rotate-keys` on a schedule, removes the retired keys past their retention,
 * activates the pending key once it has been published for `keyActivationDelay` — retiring the active one —, and
 * creates the next pending key. Clients that cache the key set thus see each key before it signs and keep it until
 * the last token it signed has expired. A provider without any key creates and activates one at once.
 *
 * The first key and each rotation are made under one lock, `phil_identity_provider.signing_keys`, the keys read again
 * once it is held: instances that start or rotate at the same time make one key, not one each. Every read still
 * settles what a lock not shared by every server, or keys written before, may have left: of several active keys, all
 * but the one activated last — the greatest identifier breaking a tie — are retired, and stay published for the tokens
 * they signed; of several pending keys, all but the oldest are removed, having signed nothing. Every instance settles
 * the same way, so that they all sign with the same key.
 *
 * The key that signs is decrypted and loaded once per process, then kept in `LoadedKeys` under its identifier, which
 * names one key for its whole life: a rotation names another key, which is loaded in turn.
 */
final readonly class SigningKeys implements SigningKeySource
{
    public const string LOCK = 'phil_identity_provider.signing_keys';

    /**
     * @param LockFactory $locks its store shared by every server of the provider
     */
    public function __construct(
        private SigningKeyRepository $keys,
        private SecretCipher $cipher,
        private ProviderSettings $settings,
        private ClockInterface $clock,
        private LockFactory $locks,
        private ?Journal $journal = null,
        private LoadedKeys $loaded = new LoadedKeys(),
    ) {
    }

    /**
     * The key that signs now.
     *
     * @phpstan-impure
     */
    #[Override]
    public function active(): PrivateKey
    {
        $active = array_find($this->settled(), static fn (SigningKey $key): bool => $key->isActive()) ?? $this->exclusively(function (): SigningKey {
            $active = array_find($this->settled(), static fn (SigningKey $key): bool => $key->isActive());

            if (null === $active) {
                $active = $this->create();
                $active->activate($this->clock->now());
                $this->keys->save($active);
            }

            return $active;
        });

        return $this->loaded->get($active->kid(), fn (): PrivateKey => PrivateKey::fromMaterial($active->algorithm(), $active->kid(), $this->cipher->open($active->privateMaterial(), $this->context($active->kid()))));
    }

    /**
     * The JSON Web Key Set: every key published, pending, active or retired within its retention.
     *
     * @return array{keys: list<array<string, string>>}
     *
     * @phpstan-impure
     */
    #[Override]
    public function jwks(): array
    {
        return ['keys' => array_map(static fn (SigningKey $key): array => $key->publicJwk(), $this->published())];
    }

    /**
     * The published JSON Web Key of a key identifier, null when the provider does not publish it.
     */
    #[Override]
    public function publicKey(string $kid): ?JWK
    {
        $key = array_find($this->published(), static fn (SigningKey $key): bool => $key->kid() === $kid);

        return null === $key ? null : new JWK($key->publicJwk());
    }

    /**
     * @return array{removed: int, activated: ?string, created: ?string}
     *
     * @phpstan-impure
     */
    public function rotate(bool $immediately = false): array
    {
        return $this->exclusively(fn (): array => $this->rotateNow($immediately));
    }

    /**
     * @return array{removed: int, activated: ?string, created: ?string}
     */
    private function rotateNow(bool $immediately): array
    {
        $now = $this->clock->now();
        $removed = 0;
        [$activated, $created] = [null, null];

        foreach ($this->keys->all() as $key) {
            if (null !== $key->retiredAt() && $key->retiredAt()->getTimestamp() + $this->settings->retiredKeyRetention < $now->getTimestamp()) {
                $this->keys->remove($key);
                ++$removed;
            }
        }

        $keys = $this->settled();
        $pending = array_find($keys, static fn (SigningKey $key): bool => $key->isPending());
        $active = array_find($keys, static fn (SigningKey $key): bool => $key->isActive());

        if (null !== $pending && (null === $active || $immediately || $pending->createdAt()->getTimestamp() + $this->settings->keyActivationDelay <= $now->getTimestamp())) {
            $active?->retire($now);
            $pending->activate($now);
            $this->keys->save(...array_filter([$active, $pending]));
            $activated = $pending->kid();
            $pending = null;
        }

        if (null === $pending) {
            $next = $this->create();

            if (null === $activated && null === $active) {
                $next->activate($now);
                $activated = $next->kid();
            }

            $this->keys->save($next);
            $created = $next->kid();
        }

        $this->journal?->record(IdentityEventType::SigningKeyRotated, null, ['activated' => $activated, 'created' => $created, 'removed' => $removed]);

        return ['removed' => $removed, 'activated' => $activated, 'created' => $created];
    }

    /**
     * Runs the work under the lock of the keys, waiting for another instance to release it; the lock is released by the
     * work's end, not left to the destruction of its object.
     *
     * @template T
     *
     * @param Closure(): T $work
     *
     * @return T
     */
    private function exclusively(Closure $work): mixed
    {
        $lock = $this->locks->createLock(self::LOCK, autoRelease: false);
        $lock->acquire(true);

        try {
            return $work();
        } finally {
            $lock->release();
        }
    }

    /**
     * Every key, at most one of them active and one pending, the extra ones retired or removed.
     *
     * @return list<SigningKey>
     */
    private function settled(): array
    {
        $keys = $this->keys->all();
        $active = array_filter($keys, static fn (SigningKey $key): bool => $key->isActive());
        $pending = array_filter($keys, static fn (SigningKey $key): bool => $key->isPending());

        if (\count($active) < 2 && \count($pending) < 2) {
            return $keys;
        }

        usort($active, static fn (SigningKey $a, SigningKey $b): int => [$b->activatedAt(), $b->kid()] <=> [$a->activatedAt(), $a->kid()]);
        $retired = \array_slice($active, 1);
        $now = $this->clock->now();

        foreach ($retired as $key) {
            $key->retire($now);
        }

        $this->keys->save(...$retired);
        $removed = \array_slice($pending, 1);

        foreach ($removed as $key) {
            $this->keys->remove($key);
        }

        $this->journal?->record(IdentityEventType::SigningKeyRotated, null, ['retired' => implode(' ', array_map(static fn (SigningKey $key): string => $key->kid(), $retired)), 'removed' => \count($removed)]);

        return $this->keys->all();
    }

    /**
     * @return list<SigningKey>
     */
    private function published(): array
    {
        $now = $this->clock->now()->getTimestamp();

        return array_values(array_filter($this->keys->all(), fn (SigningKey $key): bool => !$key->retiredAt() instanceof DateTimeImmutable || $key->retiredAt()->getTimestamp() + $this->settings->retiredKeyRetention >= $now));
    }

    private function create(): SigningKey
    {
        $private = PrivateKey::generate($this->settings->algorithm);

        return SigningKey::create($private->kid, $private->algorithm, $this->cipher->seal($private->material(), $this->context($private->kid)), $private->publicJwk(), $this->clock->now());
    }

    private function context(string $kid): string
    {
        return 'signing-key:'.$kid;
    }
}
