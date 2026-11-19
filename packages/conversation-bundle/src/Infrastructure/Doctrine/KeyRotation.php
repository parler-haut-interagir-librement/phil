<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Phil\ConversationBundle\Application\Contract\MessageSealer;
use Phil\ConversationBundle\Domain\Exception\UnreadableMessage;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\ConversationBundle\Domain\ValueObject\MessageId;

/**
 * Seals again, under the current master key, the subjects and bodies sealed under a previous one, by batches, so that
 * the previous key can leave the key management service once none remains. Each text is opened under the key it names,
 * which the service must still hold, and written back only if no other request changed it meanwhile.
 */
final readonly class KeyRotation
{
    public const int BATCH = 500;

    public function __construct(
        private Connection $connection,
        private MessageSealer $sealer,
    ) {
    }

    /**
     * How many subjects and bodies are sealed under another key than the current one.
     *
     * @return array{subjects: int, bodies: int}
     */
    public function pending(): array
    {
        $key = $this->sealer->currentKey();

        return [
            'subjects' => $this->integer($this->connection->fetchOne('SELECT COUNT(*) FROM '.ConversationTables::CONVERSATION.' WHERE subject_key IS NOT NULL AND subject_key <> ?', [$key])),
            'bodies' => $this->integer($this->connection->fetchOne('SELECT COUNT(*) FROM '.ConversationTables::MESSAGE.' WHERE body_key IS NOT NULL AND body_key <> ?', [$key])),
        ];
    }

    /**
     * Seals one batch of each again.
     *
     * @param positive-int $batch
     *
     * @return array{subjects: int, bodies: int} how many were sealed again
     *
     * @throws UnreadableMessage when a text does not open, its key gone from the service
     */
    public function rotate(int $batch = self::BATCH): array
    {
        $key = $this->sealer->currentKey();
        $platform = $this->connection->getDatabasePlatform();
        $subjects = 0;
        $bodies = 0;

        $rows = $this->connection->fetchAllAssociative($platform->modifyLimitQuery('SELECT id, subject FROM '.ConversationTables::CONVERSATION.' WHERE subject_key IS NOT NULL AND subject_key <> ? ORDER BY id', $batch), [$key]);

        foreach ($rows as $row) {
            $id = ConversationId::fromString($this->text($row['id']));
            $old = $this->text($row['subject']);
            $context = DbalConversationRepository::subjectContext($id);
            $subjects += $this->integer($this->connection->executeStatement(
                'UPDATE '.ConversationTables::CONVERSATION.' SET subject = ?, subject_key = ? WHERE id = ? AND subject = ?',
                [$this->sealer->seal($this->sealer->open($old, $context), $context), $key, $id->value, $old],
            ));
        }

        $rows = $this->connection->fetchAllAssociative($platform->modifyLimitQuery('SELECT seq, id, body FROM '.ConversationTables::MESSAGE.' WHERE body_key IS NOT NULL AND body_key <> ? ORDER BY seq', $batch), [$key]);

        foreach ($rows as $row) {
            $id = MessageId::fromString($this->text($row['id']));
            $old = $this->text($row['body']);
            $context = DbalConversationRepository::messageContext($id);
            $bodies += $this->integer($this->connection->executeStatement(
                'UPDATE '.ConversationTables::MESSAGE.' SET body = ?, body_key = ? WHERE seq = ? AND body = ?',
                [$this->sealer->seal($this->sealer->open($old, $context), $context), $key, $row['seq'], $old],
                [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::STRING],
            ));
        }

        return ['subjects' => $subjects, 'bodies' => $bodies];
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function text(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
