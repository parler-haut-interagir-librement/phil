<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Override;
use Phil\AuditBundle\Domain\Contract\FeedCheckpoints;

/**
 * The feeds' places in a table of the trail's connection, `phil_audit_feed`: read on its key, inserted the first
 * time, and only ever moved forward — an update that would move a place back changes nothing.
 */
final readonly class DbalFeedCheckpoints implements FeedCheckpoints
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function position(string $feed, string $segment): int
    {
        $sequence = $this->connection->fetchOne(\sprintf('SELECT sequence FROM %s WHERE feed = ? AND segment = ?', AuditTable::FEED), [$feed, $segment]);

        return \is_scalar($sequence) ? max(0, (int) $sequence) : 0;
    }

    #[Override]
    public function advance(string $feed, string $segment, int $sequence): void
    {
        if ($sequence < 1 || $this->moveForward($feed, $segment, $sequence)) {
            return;
        }

        try {
            $this->connection->insert(AuditTable::FEED, ['feed' => $feed, 'segment' => $segment, 'sequence' => $sequence], [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER]);
        } catch (UniqueConstraintViolationException) {
            // The place is there already — at or past this sequence, or inserted by another run since —: move it
            // forward from there, if it is behind.
            $this->moveForward($feed, $segment, $sequence);
        }
    }

    /**
     * Whether the place was behind the sequence, and moved to it; not when it is at or past it, or missing.
     */
    private function moveForward(string $feed, string $segment, int $sequence): bool
    {
        return 0 < $this->connection->executeStatement(
            \sprintf('UPDATE %s SET sequence = ? WHERE feed = ? AND segment = ? AND sequence < ?', AuditTable::FEED),
            [$sequence, $feed, $segment, $sequence],
            [ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER],
        );
    }
}
