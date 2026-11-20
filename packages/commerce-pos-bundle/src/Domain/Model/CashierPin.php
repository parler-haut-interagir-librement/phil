<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The PIN by which a cashier takes over a shared register, linked to a user of the application and kept only as a
 * hash. It is no account of its own: the application signs the user in as it always does, and the PIN only switches
 * who rings up on a device someone already unlocked.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_pos_cashier_pin')]
final class CashierPin
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'user_identifier', length: 255)]
        public readonly string $user,
        #[ORM\Column(name: 'hash', length: 255)]
        private string $hash,
        #[ORM\Column(name: 'set_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $setAt,
    ) {
    }

    /**
     * @param string $hash the PIN as a password hasher hashed it, never the PIN itself
     */
    public static function of(string $user, string $hash, DateTimeImmutable $at): self
    {
        if ('' === trim($user) || mb_strlen($user) > 255) {
            throw InvalidValue::because('A cashier is named in 1 to 255 characters.');
        }

        return new self($user, self::checkedHash($hash), $at);
    }

    public function hash(): string
    {
        return $this->hash;
    }

    public function setAt(): DateTimeImmutable
    {
        return $this->setAt;
    }

    public function change(string $hash, DateTimeImmutable $at): void
    {
        $this->hash = self::checkedHash($hash);
        $this->setAt = $at;
    }

    private static function checkedHash(string $hash): string
    {
        if ('' === $hash || \strlen($hash) > 255) {
            throw InvalidValue::because('A PIN hash is 1 to 255 bytes.');
        }

        return $hash;
    }
}
