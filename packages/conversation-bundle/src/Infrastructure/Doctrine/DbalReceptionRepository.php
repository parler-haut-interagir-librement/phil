<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Override;
use Phil\ConversationBundle\Domain\Model\ReceptionSettings;
use Phil\ConversationBundle\Domain\Reception\WhoCanWrite;
use Phil\ConversationBundle\Domain\Repository\ReceptionRepository;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;

/**
 * The reception settings kept through DBAL: a row for whom a participant lets write to them, when it is not everyone,
 * and a row per person they blocked.
 */
final readonly class DbalReceptionRepository implements ReceptionRepository
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function of(ParticipantId $participant): ReceptionSettings
    {
        $who = $this->connection->fetchOne('SELECT who_can_write FROM '.ConversationTables::RECEPTION.' WHERE participant = ?', [$participant->value]);
        $blocked = $this->connection->fetchFirstColumn('SELECT blocked FROM '.ConversationTables::BLOCK.' WHERE participant = ? ORDER BY blocked', [$participant->value]);

        return new ReceptionSettings(
            $participant,
            (\is_string($who) ? WhoCanWrite::tryFrom($who) : null) ?? WhoCanWrite::Everyone,
            array_map(static fn (mixed $other): ParticipantId => ParticipantId::fromString(\is_string($other) ? $other : ''), $blocked),
        );
    }

    #[Override]
    public function save(ReceptionSettings $settings): void
    {
        $participant = $settings->participant->value;

        $this->connection->transactional(static function (Connection $connection) use ($settings, $participant): void {
            $connection->delete(ConversationTables::RECEPTION, ['participant' => $participant]);

            if (WhoCanWrite::Everyone !== $settings->whoCanWrite()) {
                $connection->insert(ConversationTables::RECEPTION, ['participant' => $participant, 'who_can_write' => $settings->whoCanWrite()->value]);
            }

            $connection->delete(ConversationTables::BLOCK, ['participant' => $participant]);

            foreach ($settings->blocked() as $other) {
                $connection->insert(ConversationTables::BLOCK, ['participant' => $participant, 'blocked' => $other->value]);
            }
        });
    }

    #[Override]
    public function forget(ParticipantId $participant): int
    {
        return $this->connection->transactional(static function (Connection $connection) use ($participant): int {
            $settings = $connection->delete(ConversationTables::RECEPTION, ['participant' => $participant->value]);
            $blocks = $connection->executeStatement('DELETE FROM '.ConversationTables::BLOCK.' WHERE participant = ? OR blocked = ?', [$participant->value, $participant->value]);

            return (int) $settings + (int) $blocks;
        });
    }
}
