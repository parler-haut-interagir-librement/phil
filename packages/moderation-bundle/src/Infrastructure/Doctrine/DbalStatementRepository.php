<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\ModerationBundle\Domain\Contract\StatementRepository;
use Phil\ModerationBundle\Domain\Exception\InvalidModeration;
use Phil\ModerationBundle\Domain\Status\DecisionAction;
use Phil\ModerationBundle\Domain\Status\Ground;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\StatementOfReasons;
use Phil\ModerationBundle\Domain\ValueObject\TargetReference;

/**
 * The statements of reasons in `phil_moderation_statement`, through DBAL: one insert each, deleted with the erasure of
 * the member they are addressed to.
 */
final readonly class DbalStatementRepository implements StatementRepository
{
    private const string COLUMNS = 'id, decision_id, recipient, target_type, target_id, action, reason, ground, reference, explanation, prompted, automated_detection, issued_at';

    private Rows $rows;

    public function __construct(
        private Connection $connection,
    ) {
        $this->rows = new Rows($connection);
    }

    #[Override]
    public function add(StatementOfReasons $statement): void
    {
        $this->connection->insert(ModerationTables::STATEMENTS, [
            'id' => $statement->id,
            'decision_id' => $statement->decisionId,
            'recipient' => $statement->recipient->value,
            'target_type' => $statement->target->type,
            'target_id' => $statement->target->id,
            'action' => $statement->action->value,
            'reason' => $statement->reason,
            'ground' => $statement->ground->value,
            'reference' => $statement->reference,
            'explanation' => $statement->explanation,
            'prompted' => $statement->prompted,
            'automated_detection' => $statement->automatedDetection,
            'issued_at' => $statement->issuedAt,
        ], [
            'prompted' => ParameterType::BOOLEAN,
            'automated_detection' => ParameterType::BOOLEAN,
            'issued_at' => Types::DATETIME_UTC_IMMUTABLE,
        ]);
    }

    #[Override]
    public function find(string $id): ?StatementOfReasons
    {
        return $this->one('id = ?', $id);
    }

    #[Override]
    public function forDecision(string $decisionId): ?StatementOfReasons
    {
        return $this->one('decision_id = ?', $decisionId);
    }

    #[Override]
    public function addressedTo(MemberId $recipient, int $limit, int $offset = 0): array
    {
        $sql = $this->connection->getDatabasePlatform()->modifyLimitQuery(
            'SELECT '.self::COLUMNS.' FROM '.ModerationTables::STATEMENTS.' WHERE recipient = ? ORDER BY issued_at DESC, id DESC',
            $limit,
            $offset,
        );

        return array_values(array_filter(array_map($this->hydrate(...), $this->connection->fetchAllAssociative($sql, [$recipient->value]))));
    }

    #[Override]
    public function countAddressedTo(MemberId $recipient): int
    {
        return $this->rows->number(['count' => $this->connection->fetchOne(
            'SELECT COUNT(*) FROM '.ModerationTables::STATEMENTS.' WHERE recipient = ?',
            [$recipient->value],
        )], 'count');
    }

    #[Override]
    public function erase(MemberId $recipient): int
    {
        return (int) $this->connection->delete(ModerationTables::STATEMENTS, ['recipient' => $recipient->value]);
    }

    private function one(string $where, string $value): ?StatementOfReasons
    {
        $row = $this->connection->fetchAssociative('SELECT '.self::COLUMNS.' FROM '.ModerationTables::STATEMENTS.' WHERE '.$where, [$value]);

        return false === $row ? null : $this->hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ?StatementOfReasons
    {
        $action = DecisionAction::tryFrom($this->rows->required($row, 'action'));
        $ground = Ground::tryFrom($this->rows->required($row, 'ground'));

        if (!$action instanceof DecisionAction || !$ground instanceof Ground) {
            return null;
        }

        try {
            return new StatementOfReasons(
                $this->rows->required($row, 'id'),
                $this->rows->required($row, 'decision_id'),
                MemberId::of($this->rows->required($row, 'recipient')),
                TargetReference::of($this->rows->required($row, 'target_type'), $this->rows->required($row, 'target_id')),
                $action,
                $this->rows->required($row, 'reason'),
                $ground,
                $this->rows->text($row, 'reference'),
                $this->rows->text($row, 'explanation'),
                $this->rows->flag($row, 'prompted'),
                $this->rows->flag($row, 'automated_detection'),
                $this->rows->moment($row, 'issued_at'),
            );
        } catch (InvalidModeration) {
            return null;
        }
    }
}
