<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\ModerationBundle\Domain\Contract\MemberNoteRepository;
use Phil\ModerationBundle\Domain\Exception\InvalidModeration;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\MemberNote;

/**
 * The notes on members in `phil_moderation_note`, through DBAL.
 */
final readonly class DbalMemberNoteRepository implements MemberNoteRepository
{
    private const string COLUMNS = 'id, member, author, body, written_at';

    private Rows $rows;

    public function __construct(
        private Connection $connection,
    ) {
        $this->rows = new Rows($connection);
    }

    #[Override]
    public function add(MemberNote $note): void
    {
        $this->connection->insert(ModerationTables::NOTES, [
            'id' => $note->id,
            'member' => $note->member->value,
            'author' => $note->author?->value,
            'body' => $note->body,
            'written_at' => $note->writtenAt,
        ], ['written_at' => Types::DATETIME_UTC_IMMUTABLE]);
    }

    #[Override]
    public function find(string $id): ?MemberNote
    {
        $row = $this->connection->fetchAssociative('SELECT '.self::COLUMNS.' FROM '.ModerationTables::NOTES.' WHERE id = ?', [$id]);

        return false === $row ? null : $this->hydrate($row);
    }

    #[Override]
    public function about(MemberId $member): array
    {
        return array_values(array_filter(array_map($this->hydrate(...), $this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS.' FROM '.ModerationTables::NOTES.' WHERE member = ? ORDER BY written_at DESC, id DESC',
            [$member->value],
        ))));
    }

    #[Override]
    public function remove(string $id): bool
    {
        return 1 === (int) $this->connection->delete(ModerationTables::NOTES, ['id' => $id]);
    }

    #[Override]
    public function erase(MemberId $member): int
    {
        return $this->connection->transactional(function () use ($member): int {
            $deleted = (int) $this->connection->delete(ModerationTables::NOTES, ['member' => $member->value]);

            return $deleted + (int) $this->connection->executeStatement('UPDATE '.ModerationTables::NOTES.' SET author = NULL WHERE author = ?', [$member->value]);
        });
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ?MemberNote
    {
        try {
            return new MemberNote(
                $this->rows->required($row, 'id'),
                MemberId::of($this->rows->required($row, 'member')),
                MemberId::orNull($this->rows->text($row, 'author')),
                $this->rows->required($row, 'body'),
                $this->rows->moment($row, 'written_at'),
            );
        } catch (InvalidModeration) {
            return null;
        }
    }
}
