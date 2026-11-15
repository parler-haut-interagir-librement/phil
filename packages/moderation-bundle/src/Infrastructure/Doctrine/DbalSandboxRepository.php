<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\ModerationBundle\Domain\Contract\SandboxRepository;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;

/**
 * The members who left the sandbox, in `phil_moderation_sandbox`: one row each, the first approval kept.
 */
final readonly class DbalSandboxRepository implements SandboxRepository
{
    private Rows $rows;

    public function __construct(
        private Connection $connection,
    ) {
        $this->rows = new Rows($connection);
    }

    #[Override]
    public function clearedAt(MemberId $member): ?DateTimeImmutable
    {
        $row = $this->connection->fetchAssociative('SELECT cleared_at FROM '.ModerationTables::SANDBOX.' WHERE member = ?', [$member->value]);

        return false === $row ? null : $this->rows->moment($row, 'cleared_at');
    }

    #[Override]
    public function clear(MemberId $member, DateTimeImmutable $at): void
    {
        if ($this->clearedAt($member) instanceof DateTimeImmutable) {
            return;
        }

        try {
            $this->connection->insert(ModerationTables::SANDBOX, ['member' => $member->value, 'cleared_at' => $at], ['cleared_at' => Types::DATETIME_UTC_IMMUTABLE]);
        } catch (UniqueConstraintViolationException) {
            // Another request approved a first post of the member at the same moment: they left the sandbox.
        }
    }

    #[Override]
    public function forget(MemberId $member): bool
    {
        return 1 === (int) $this->connection->delete(ModerationTables::SANDBOX, ['member' => $member->value]);
    }
}
