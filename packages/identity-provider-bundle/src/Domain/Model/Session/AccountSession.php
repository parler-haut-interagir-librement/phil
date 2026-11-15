<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Model\Session;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * A signed-in session of an account, as a row of `phil_identity_session`: an opaque identifier the PHP session holds,
 * the device it was opened on — a browser and a system, read coarsely from the user agent, never the address —, the
 * methods proven at sign-in (RFC 8176), whether it looked risky, and when it was opened, last seen and closed.
 *
 * A closed row ends the PHP session that holds it at its next request: this is how a session is closed from another
 * device.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_identity_session_account', columns: ['account_id'])]
final class AccountSession
{
    public const string TABLE = 'phil_identity_session';

    public const int DEVICE_MAX_LENGTH = 100;

    /**
     * @param list<string> $methods
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 43, options: ['fixed' => true])]
        private readonly string $id,
        #[ORM\Column(name: 'account_id', length: 26, options: ['fixed' => true])]
        private readonly string $accountId,
        #[ORM\Column(name: 'device', length: self::DEVICE_MAX_LENGTH)]
        private readonly string $device,
        #[ORM\Column(name: 'methods', type: 'json')]
        private array $methods,
        #[ORM\Column(name: 'risky')]
        private readonly bool $risky,
        #[ORM\Column(name: 'created_at')]
        private readonly DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'last_seen_at')]
        private DateTimeImmutable $lastSeenAt,
        #[ORM\Column(name: 'closed_at', nullable: true)]
        private ?DateTimeImmutable $closedAt = null,
    ) {
    }

    /**
     * @param list<string> $methods
     */
    public static function open(string $id, string $accountId, string $device, array $methods, bool $risky, DateTimeImmutable $now): self
    {
        return new self($id, $accountId, mb_substr('' === trim($device) ? '?' : trim($device), 0, self::DEVICE_MAX_LENGTH), array_values(array_unique($methods)), $risky, $now, $now);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function device(): string
    {
        return $this->device;
    }

    /**
     * @return list<string>
     */
    public function methods(): array
    {
        return $this->methods;
    }

    /**
     * @param list<string> $methods
     */
    public function addMethods(array $methods): void
    {
        $this->methods = array_values(array_unique([...$this->methods, ...$methods]));
    }

    public function isRisky(): bool
    {
        return $this->risky;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function lastSeenAt(): DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function see(DateTimeImmutable $now): void
    {
        $this->lastSeenAt = $now;
    }

    public function closedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function isClosed(): bool
    {
        return $this->closedAt instanceof DateTimeImmutable;
    }

    public function close(DateTimeImmutable $now): void
    {
        $this->closedAt ??= $now;
    }
}
