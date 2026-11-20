<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceSyndicationBundle\Domain\Partner\PartnerRole;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * Another application this one trades with: who it is — its code is its own name for itself, which the messages sent
 * to it are addressed to —, where it receives messages, and the public key its messages are verified with.
 *
 * A reseller also has the products it is fed, and the share of the retail price it keeps: what it owes for a unit is
 * the retail price less that share.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_syndication_partner')]
final class Partner
{
    #[ORM\Column(name: 'active')]
    private bool $active = true;

    /** @var list<string> */
    #[ORM\Column(name: 'skus', type: 'json')]
    private array $skus = [];

    #[ORM\Column(name: 'share', type: 'integer')]
    private int $share = 0;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'code', length: 63)]
        public readonly string $code,
        #[ORM\Column(name: 'name', length: 255)]
        public readonly string $name,
        #[ORM\Column(name: 'role', length: 16, enumType: PartnerRole::class)]
        public readonly PartnerRole $role,
        #[ORM\Column(name: 'endpoint', length: 2048)]
        private string $endpoint,
        #[ORM\Column(name: 'public_key', length: 64)]
        private string $publicKey,
    ) {
    }

    /**
     * @param string $endpoint  the https URL its messages are delivered to
     * @param string $publicKey its `whpk_` Ed25519 public key, which verifies its messages
     */
    public static function of(string $code, string $name, PartnerRole $role, string $endpoint, string $publicKey): self
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,62}$/D', $code)) {
            throw InvalidValue::because(\sprintf('"%s" is not a partner code: up to 63 lowercase letters, digits, "_", "." or "-".', $code));
        }

        if ('' === trim($name)) {
            throw InvalidValue::because(\sprintf('The partner %s has a name.', $code));
        }

        return new self($code, $name, $role, self::checkedEndpoint($endpoint), self::checkedKey($publicKey));
    }

    public function endpoint(): string
    {
        return $this->endpoint;
    }

    public function publicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * @return list<string>
     */
    public function skus(): array
    {
        return $this->skus;
    }

    public function share(): int
    {
        return $this->share;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Moves its endpoint or rotates its key.
     */
    public function reach(string $endpoint, string $publicKey): void
    {
        $this->endpoint = self::checkedEndpoint($endpoint);
        $this->publicKey = self::checkedKey($publicKey);
    }

    /**
     * The products a reseller is fed, and the share of their retail price it keeps, in parts per million.
     *
     * @param list<string> $skus
     */
    public function feed(array $skus, int $share): void
    {
        if (PartnerRole::Reseller !== $this->role) {
            throw InvalidValue::because(\sprintf('Only a reseller is fed: %s is a supplier.', $this->code));
        }

        foreach ($skus as $sku) {
            if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $sku)) {
                throw InvalidValue::because(\sprintf('"%s" is not a SKU: 1 to 64 visible ASCII characters.', $sku));
            }
        }

        if ($share < 0 || $share >= 1_000_000) {
            throw InvalidValue::because(\sprintf('A reseller keeps 0 to 999999 parts per million; %d is refused.', $share));
        }

        $this->skus = array_values(array_unique($skus));
        $this->share = $share;
    }

    public function feeds(string $sku): bool
    {
        return $this->active && \in_array($sku, $this->skus, true);
    }

    public function suspend(): void
    {
        $this->active = false;
    }

    public function resume(): void
    {
        $this->active = true;
    }

    private static function checkedEndpoint(string $endpoint): string
    {
        if (\strlen($endpoint) > 2048 || 1 !== preg_match('#^https://[\x21-\x7E]+$#D', $endpoint)) {
            throw InvalidValue::because(\sprintf('"%s" is not an https endpoint.', $endpoint));
        }

        return $endpoint;
    }

    private static function checkedKey(string $publicKey): string
    {
        if (1 !== preg_match('#^whpk_[A-Za-z0-9+/]{43}=$#D', $publicKey)) {
            throw InvalidValue::because('A partner key is a "whpk_" Ed25519 public key.');
        }

        return $publicKey;
    }
}
