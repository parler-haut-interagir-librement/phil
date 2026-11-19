<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\ReactionsBundle\Domain\Exception\AlreadyReacted;
use Phil\ReactionsBundle\Domain\Model\Reaction;
use Phil\ReactionsBundle\Domain\Repository\ReactionRepository;
use Phil\Resource\ResourceReference;

/**
 * The reactions in the table `phil_reaction`, in plain queries. A duplicate two requests raced to add is refused by the
 * unique constraint without closing any entity manager, and saving a reaction flushes nothing else of the application.
 *
 * A counter is read from the index on the content and the code, without touching the rows.
 */
final readonly class DoctrineReactionRepository implements ReactionRepository
{
    private const string TABLE = Reaction::TABLE;

    /** The moments are written and read back in UTC, as the mapping declares the column. */
    private const string MOMENT = Types::DATETIME_UTC_IMMUTABLE;

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function of(ResourceReference $content, string $member): array
    {
        /** @var list<array{id: string, content: string, member_id: string, code: string, reacted_at: string}> $rows */
        $rows = $this->connection->fetchAllAssociative('SELECT id, content, member_id, code, reacted_at FROM '.self::TABLE.' WHERE content = ? AND member_id = ? ORDER BY id', [$content->toString(), $member]);

        return array_map($this->restored(...), $rows);
    }

    #[Override]
    public function add(Reaction $reaction): void
    {
        try {
            $this->connection->insert(self::TABLE, [
                'id' => $reaction->id,
                'content' => $reaction->content,
                'member_id' => $reaction->member,
                'code' => $reaction->code(),
                'reacted_at' => $reaction->reactedAt(),
            ], ['reacted_at' => self::MOMENT]);
        } catch (UniqueConstraintViolationException) {
            throw AlreadyReacted::on($reaction->content, $reaction->code());
        }
    }

    #[Override]
    public function save(Reaction $reaction): void
    {
        $this->connection->update(self::TABLE, ['code' => $reaction->code(), 'reacted_at' => $reaction->reactedAt()], ['id' => $reaction->id], ['reacted_at' => self::MOMENT]);
    }

    #[Override]
    public function remove(Reaction $reaction): void
    {
        $this->connection->delete(self::TABLE, ['id' => $reaction->id]);
    }

    #[Override]
    public function counts(array $contents): array
    {
        /** @var list<array{content: string, code: string, total: int|string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT content, code, COUNT(*) AS total FROM '.self::TABLE.' WHERE content IN (?) GROUP BY content, code',
            [$contents],
            [ArrayParameterType::STRING],
        );
        $counts = [];

        foreach ($rows as $row) {
            $counts[$row['content']][$row['code']] = (int) $row['total'];
        }

        return $counts;
    }

    #[Override]
    public function chosenBy(string $member, array $contents): array
    {
        /** @var list<array{content: string, code: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT content, code FROM '.self::TABLE.' WHERE member_id = ? AND content IN (?) ORDER BY id',
            [$member, $contents],
            [ParameterType::STRING, ArrayParameterType::STRING],
        );
        $chosen = [];

        foreach ($rows as $row) {
            $chosen[$row['content']][] = $row['code'];
        }

        return $chosen;
    }

    #[Override]
    public function reactors(array $contents, int $perCode): array
    {
        /** @var list<array{content: string, code: string, member_id: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT content, code, member_id FROM ('
            .'SELECT content, code, member_id, ROW_NUMBER() OVER (PARTITION BY content, code ORDER BY id) AS position FROM '.self::TABLE.' WHERE content IN (?)'
            .') ranked WHERE position <= ? ORDER BY position',
            [$contents, $perCode],
            [ArrayParameterType::STRING, ParameterType::INTEGER],
        );
        $reactors = [];

        foreach ($rows as $row) {
            $reactors[$row['content']][$row['code']][] = $row['member_id'];
        }

        return $reactors;
    }

    #[Override]
    public function ofMember(string $member): array
    {
        /** @var list<array{id: string, content: string, member_id: string, code: string, reacted_at: string}> $rows */
        $rows = $this->connection->fetchAllAssociative('SELECT id, content, member_id, code, reacted_at FROM '.self::TABLE.' WHERE member_id = ? ORDER BY id', [$member]);

        return array_map($this->restored(...), $rows);
    }

    #[Override]
    public function eraseMember(string $member): void
    {
        $this->connection->delete(self::TABLE, ['member_id' => $member]);
    }

    #[Override]
    public function eraseContent(ResourceReference $content): void
    {
        $this->connection->delete(self::TABLE, ['content' => $content->toString()]);
    }

    /**
     * @param array{id: string, content: string, member_id: string, code: string, reacted_at: string} $row
     */
    private function restored(array $row): Reaction
    {
        /** @var DateTimeImmutable $reactedAt */
        $reactedAt = $this->connection->convertToPHPValue($row['reacted_at'], self::MOMENT);

        return Reaction::restored($row['id'], $row['content'], $row['member_id'], $row['code'], $reactedAt);
    }
}
