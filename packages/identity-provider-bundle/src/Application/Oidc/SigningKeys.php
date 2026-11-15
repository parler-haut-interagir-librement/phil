<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Oidc;

use DateTimeImmutable;
use Jose\Component\Core\JWK;
use Phil\IdentityProviderBundle\Application\Contract\SecretCipher;
use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Jose\PrivateKey;
use Phil\IdentityProviderBundle\Application\Journal;
use Phil\IdentityProviderBundle\Domain\Contract\SigningKeyRepository;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\SigningKey;
use Psr\Clock\ClockInterface;

/**
 * The keys of the provider and their rotation.
 *
 * `rotate()`, run by `phil:identity-provider:rotate-keys` on a schedule, removes the retired keys past their retention,
 * activates the pending key once it has been published for `keyActivationDelay` — retiring the active one —, and
 * creates the next pending key. Clients that cache the key set thus see each key before it signs and keep it until
 * the last token it signed has expired. A provider without any key creates and activates one at once.
 */
final readonly class SigningKeys
{
    public function __construct(
        private SigningKeyRepository $keys,
        private SecretCipher $cipher,
        private ProviderSettings $settings,
        private ClockInterface $clock,
        private ?Journal $journal = null,
    ) {
    }

    /**
     * The key that signs now.
     *
     * @phpstan-impure
     */
    public function active(): PrivateKey
    {
        $active = array_find($this->keys->all(), static fn (SigningKey $key): bool => $key->isActive());

        if (null === $active) {
            $active = $this->create();
            $active->activate($this->clock->now());
            $this->keys->save($active);
        }

        return PrivateKey::fromMaterial($active->algorithm(), $active->kid(), $this->cipher->open($active->privateMaterial(), $this->context($active->kid())));
    }

    /**
     * The JSON Web Key Set: every key published, pending, active or retired within its retention.
     *
     * @return array{keys: list<array<string, string>>}
     *
     * @phpstan-impure
     */
    public function jwks(): array
    {
        return ['keys' => array_map(static fn (SigningKey $key): array => $key->publicJwk(), $this->published())];
    }

    /**
     * The published JSON Web Key of a key identifier, null when the provider does not publish it.
     */
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
        $now = $this->clock->now();
        $removed = 0;
        [$activated, $created] = [null, null];

        foreach ($this->keys->all() as $key) {
            if (null !== $key->retiredAt() && $key->retiredAt()->getTimestamp() + $this->settings->retiredKeyRetention < $now->getTimestamp()) {
                $this->keys->remove($key);
                ++$removed;
            }
        }

        $keys = $this->keys->all();
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
