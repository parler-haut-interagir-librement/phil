<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\SitesConsoleBundle\Domain\Status\JournalAction;
use Symfony\Component\Uid\Ulid;

/**
 * One line of the journal: who did what to which site, when, and how it went. Lines are only ever added; their
 * identifiers sort in the order they were written.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_sites_console_journal_site', columns: ['site_id'])]
final class JournalEntry
{
    public const string TABLE = 'phil_sites_console_journal';

    public const int MESSAGE_MAX_LENGTH = 500;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 26, options: ['fixed' => true])]
        private readonly string $id,
        #[ORM\Column(name: 'occurred_at')]
        private readonly DateTimeImmutable $occurredAt,
        #[ORM\Column(name: 'actor', length: 180, nullable: true)]
        private readonly ?string $actor,
        #[ORM\Column(name: 'action', length: 32, enumType: JournalAction::class)]
        private readonly JournalAction $action,
        #[ORM\Column(name: 'site_id', length: 64, nullable: true)]
        private readonly ?string $siteId,
        #[ORM\Column(name: 'reference', length: 128, nullable: true)]
        private readonly ?string $reference,
        #[ORM\Column(name: 'succeeded')]
        private readonly bool $succeeded,
        #[ORM\Column(name: 'message', length: self::MESSAGE_MAX_LENGTH)]
        private readonly string $message,
    ) {
    }

    /**
     * @param ?string $actor     the user identifier of the operator, null for a scheduled run
     * @param ?string $reference the push or the plan
     */
    public static function record(JournalAction $action, ?string $actor, ?string $siteId, ?string $reference, bool $succeeded, string $message, DateTimeImmutable $now): self
    {
        return new self(new Ulid()->toBase32(), $now, null === $actor ? null : mb_substr($actor, 0, 180), $action, $siteId, $reference, $succeeded, mb_substr($message, 0, self::MESSAGE_MAX_LENGTH));
    }

    public function id(): string
    {
        return $this->id;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function actor(): ?string
    {
        return $this->actor;
    }

    public function action(): JournalAction
    {
        return $this->action;
    }

    public function siteId(): ?string
    {
        return $this->siteId;
    }

    public function reference(): ?string
    {
        return $this->reference;
    }

    public function succeeded(): bool
    {
        return $this->succeeded;
    }

    public function message(): string
    {
        return $this->message;
    }
}
