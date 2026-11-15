<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Model\Totp;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * The recovery codes of an account, as a row of `phil_identity_recovery_codes`: the keyed hash of each code, a used
 * code null in its place, and when they were generated. New codes replace the row.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
final class RecoveryCodeSet
{
    public const string TABLE = 'phil_identity_recovery_codes';

    /**
     * @param list<string|null> $hashes
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'account_id', length: 26, options: ['fixed' => true])]
        private readonly string $accountId,
        #[ORM\Column(name: 'hashes', type: 'json')]
        private array $hashes,
        #[ORM\Column(name: 'created_at')]
        private DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param list<string> $hashes
     */
    public static function generate(string $accountId, array $hashes, DateTimeImmutable $now): self
    {
        return new self($accountId, $hashes, $now);
    }

    /**
     * @param list<string> $hashes
     */
    public function replace(array $hashes, DateTimeImmutable $now): void
    {
        $this->hashes = $hashes;
        $this->createdAt = $now;
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    /**
     * @return list<string|null>
     */
    public function hashes(): array
    {
        return $this->hashes;
    }

    public function remaining(): int
    {
        return \count(array_filter($this->hashes, static fn (?string $hash): bool => null !== $hash));
    }

    public function use(int $position): void
    {
        if (\array_key_exists($position, $this->hashes)) {
            $this->hashes[$position] = null;
        }
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
