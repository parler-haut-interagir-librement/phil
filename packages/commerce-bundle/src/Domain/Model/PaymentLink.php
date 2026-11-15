<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Symfony\Component\Uid\Uuid;

/**
 * A link that lets whoever holds it pay a placed order, until it expires or is revoked: what a store sends by email
 * for an order taken by phone, or for the balance of an amended order.
 *
 * Its token is shown once, when it is created; only its SHA-256 is stored. The link carries no amount: whoever follows
 * it pays what the order still lacks, through `Payments`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_payment_link')]
#[ORM\Index(columns: ['order_number'])]
#[ORM\UniqueConstraint(columns: ['token_hash'])]
final class PaymentLink
{
    #[ORM\Column(name: 'revoked_at', nullable: true)]
    private ?DateTimeImmutable $revokedAt = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'token_hash', length: 64, options: ['fixed' => true])]
        private readonly string $tokenHash,
        #[ORM\Column(name: 'created_at')]
        public readonly DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'expires_at')]
        public readonly DateTimeImmutable $expiresAt,
    ) {
    }

    public static function create(string $orderNumber, string $tokenHash, DateTimeImmutable $at, DateTimeImmutable $expiresAt): self
    {
        if (1 !== preg_match('/^[0-9a-f]{64}$/D', $tokenHash)) {
            throw InvalidValue::because('A payment link is stored under the hexadecimal SHA-256 of its token.');
        }

        if ($expiresAt <= $at) {
            throw InvalidValue::because('A payment link expires after it is created.');
        }

        return new self(Uuid::v7()->toRfc4122(), $orderNumber, $tokenHash, $at, $expiresAt);
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    /**
     * Whether the link still opens its order at that moment: not revoked, and not expired.
     */
    public function opensAt(DateTimeImmutable $at): bool
    {
        return !$this->revokedAt instanceof DateTimeImmutable && $at < $this->expiresAt;
    }

    public function revoke(DateTimeImmutable $at): void
    {
        $this->revokedAt ??= $at;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }
}
