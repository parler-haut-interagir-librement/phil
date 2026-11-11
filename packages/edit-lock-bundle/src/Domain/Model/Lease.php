<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\EditLockBundle\Domain\Exception\InvalidLease;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\EditLockBundle\Domain\ValueObject\LeaseKey;

/**
 * The right of one holder to write a resource, or one part of it, until a moment: renewed by heartbeats, passed to
 * another holder once it has expired or when it is taken over, given up when released. A lease is a value; each
 * change returns a copy whose version is one more, so that a store replaces it only if nobody did in between.
 *
 * Times are UTC, to the second. The table is mapped for the application's schema tools and migrations; the stores of
 * the bundle write it by plain queries.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(columns: ['expires_at'])]
final class Lease
{
    public const string TABLE = 'phil_edit_lease';

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'resource', length: 255)]
        private readonly string $resource,
        #[ORM\Id]
        #[ORM\Column(name: 'part', length: 64)]
        private readonly string $part,
        #[ORM\Column(name: 'holder_id', length: 255)]
        private readonly string $holderId,
        #[ORM\Column(name: 'holder_name', length: 255)]
        private readonly string $holderName,
        #[ORM\Column(name: 'acquired_at')]
        private readonly DateTimeImmutable $acquiredAt,
        #[ORM\Column(name: 'expires_at')]
        private readonly DateTimeImmutable $expiresAt,
        #[ORM\Column(name: 'version')]
        public readonly int $version,
    ) {
    }

    /**
     * A new lease, from now for a number of seconds.
     *
     * @throws InvalidLease when the duration is not positive
     */
    public static function grant(LeaseKey $key, Holder $holder, DateTimeImmutable $now, int $seconds): self
    {
        $acquiredAt = self::second($now);

        return new self($key->resource, $key->part ?? '', $holder->id, $holder->name, $acquiredAt, self::until($acquiredAt, $seconds), 1);
    }

    /**
     * A lease as a store kept it.
     *
     * @throws InvalidLease when it expires before it was acquired, or its version is not positive
     */
    public static function restore(LeaseKey $key, Holder $holder, DateTimeImmutable $acquiredAt, DateTimeImmutable $expiresAt, int $version): self
    {
        if ($expiresAt < $acquiredAt) {
            throw InvalidLease::because('A lease expires after it was acquired.');
        }

        if ($version < 1) {
            throw InvalidLease::because('The version of a lease is a positive number.');
        }

        return new self($key->resource, $key->part ?? '', $holder->id, $holder->name, self::second($acquiredAt), self::second($expiresAt), $version);
    }

    public function key(): LeaseKey
    {
        return LeaseKey::of($this->resource, '' === $this->part ? null : $this->part);
    }

    public function holder(): Holder
    {
        return Holder::of($this->holderId, $this->holderName);
    }

    public function acquiredAt(): DateTimeImmutable
    {
        return $this->acquiredAt;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isHeldBy(Holder $holder): bool
    {
        return $this->holderId === $holder->id;
    }

    public function isActiveAt(DateTimeImmutable $now): bool
    {
        return $this->expiresAt > $now;
    }

    /**
     * The seconds left at a moment, none once expired.
     */
    public function secondsLeftAt(DateTimeImmutable $now): int
    {
        return max(0, $this->expiresAt->getTimestamp() - $now->getTimestamp());
    }

    /**
     * The same lease, kept by its holder for a number of seconds from now; the name they go by is the one given.
     *
     * @throws InvalidLease when the duration is not positive
     */
    public function renewed(Holder $holder, DateTimeImmutable $now, int $seconds): self
    {
        return new self($this->resource, $this->part, $this->holderId, $holder->name, $this->acquiredAt, self::until(self::second($now), $seconds), $this->version + 1);
    }

    /**
     * The lease passed to another holder, from now for a number of seconds.
     *
     * @throws InvalidLease when the duration is not positive
     */
    public function passedTo(Holder $holder, DateTimeImmutable $now, int $seconds): self
    {
        $acquiredAt = self::second($now);

        return new self($this->resource, $this->part, $holder->id, $holder->name, $acquiredAt, self::until($acquiredAt, $seconds), $this->version + 1);
    }

    private static function until(DateTimeImmutable $from, int $seconds): DateTimeImmutable
    {
        if ($seconds < 1) {
            throw InvalidLease::because('A lease lasts at least one second.');
        }

        return $from->setTimestamp($from->getTimestamp() + $seconds);
    }

    private static function second(DateTimeImmutable $moment): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.$moment->getTimestamp());
    }
}
