<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\ModerationBundle\Domain\Contract\DecisionRepository;
use Phil\ModerationBundle\Domain\Exception\InvalidModeration;
use Phil\ModerationBundle\Domain\Status\DecisionAction;
use Phil\ModerationBundle\Domain\ValueObject\Decision;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\TargetReference;

/**
 * The decisions in `phil_moderation_decision`, through DBAL: one insert each, never an update but the erasure of a
 * member.
 */
final readonly class DbalDecisionRepository implements DecisionRepository
{
    private const string COLUMNS = 'id, target_type, target_id, action, moderator, role, public_reason, note, author, decided_at, is_public';

    private const string ORDER = ' ORDER BY decided_at DESC, id DESC';

    private Rows $rows;

    public function __construct(
        private Connection $connection,
    ) {
        $this->rows = new Rows($connection);
    }

    #[Override]
    public function add(Decision $decision): void
    {
        $this->connection->insert(ModerationTables::DECISIONS, [
            'id' => $decision->id,
            'target_type' => $decision->target->type,
            'target_id' => $decision->target->id,
            'action' => $decision->action->value,
            'moderator' => $decision->moderator?->value,
            'role' => $decision->role,
            'public_reason' => $decision->publicReason,
            'note' => $decision->note,
            'author' => $decision->author?->value,
            'decided_at' => $decision->decidedAt,
            'is_public' => $decision->public,
        ], ['decided_at' => Types::DATETIME_UTC_IMMUTABLE, 'is_public' => ParameterType::BOOLEAN]);
    }

    #[Override]
    public function on(TargetReference $target): array
    {
        return $this->select('target_type = ? AND target_id = ?', [$target->type, $target->id]);
    }

    #[Override]
    public function about(MemberId $author): array
    {
        return $this->select('author = ?', [$author->value]);
    }

    #[Override]
    public function takenBy(MemberId $moderator): array
    {
        return $this->select('moderator = ?', [$moderator->value]);
    }

    #[Override]
    public function public(int $limit, int $offset = 0): array
    {
        $sql = $this->connection->getDatabasePlatform()->modifyLimitQuery(
            'SELECT '.self::COLUMNS.' FROM '.ModerationTables::DECISIONS.' WHERE is_public = ?'.self::ORDER,
            $limit,
            $offset,
        );

        return $this->hydrateAll($this->connection->fetchAllAssociative($sql, [true], [ParameterType::BOOLEAN]));
    }

    #[Override]
    public function countPublic(): int
    {
        return $this->rows->number(['count' => $this->connection->fetchOne(
            'SELECT COUNT(*) FROM '.ModerationTables::DECISIONS.' WHERE is_public = ?',
            [true],
            [ParameterType::BOOLEAN],
        )], 'count');
    }

    #[Override]
    public function forget(MemberId $member): int
    {
        return $this->connection->transactional(function () use ($member): int {
            $count = $this->rows->number(['count' => $this->connection->fetchOne(
                'SELECT COUNT(*) FROM '.ModerationTables::DECISIONS.' WHERE moderator = ? OR author = ?',
                [$member->value, $member->value],
            )], 'count');
            $this->connection->executeStatement('UPDATE '.ModerationTables::DECISIONS.' SET moderator = NULL WHERE moderator = ?', [$member->value]);
            $this->connection->executeStatement('UPDATE '.ModerationTables::DECISIONS.' SET author = NULL WHERE author = ?', [$member->value]);

            return $count;
        });
    }

    /**
     * @param list<string> $parameters
     *
     * @return list<Decision>
     */
    private function select(string $where, array $parameters): array
    {
        return $this->hydrateAll($this->connection->fetchAllAssociative('SELECT '.self::COLUMNS.' FROM '.ModerationTables::DECISIONS.' WHERE '.$where.self::ORDER, $parameters));
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<Decision>
     */
    private function hydrateAll(array $rows): array
    {
        return array_values(array_filter(array_map($this->hydrate(...), $rows)));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ?Decision
    {
        $action = DecisionAction::tryFrom($this->rows->required($row, 'action'));

        if (!$action instanceof DecisionAction) {
            return null;
        }

        try {
            return new Decision(
                $this->rows->required($row, 'id'),
                TargetReference::of($this->rows->required($row, 'target_type'), $this->rows->required($row, 'target_id')),
                $action,
                MemberId::orNull($this->rows->text($row, 'moderator')),
                $this->rows->required($row, 'role'),
                $this->rows->text($row, 'public_reason'),
                $this->rows->text($row, 'note'),
                MemberId::orNull($this->rows->text($row, 'author')),
                $this->rows->moment($row, 'decided_at'),
                $this->rows->flag($row, 'is_public'),
            );
        } catch (InvalidModeration) {
            return null;
        }
    }
}
