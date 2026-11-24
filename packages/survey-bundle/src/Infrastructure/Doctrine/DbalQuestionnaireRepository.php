<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\SurveyBundle\Domain\Contract\QuestionnaireRepository;
use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;
use Psr\Clock\ClockInterface;

/**
 * The questionnaires in their table, through Doctrine DBAL.
 */
final readonly class DbalQuestionnaireRepository implements QuestionnaireRepository
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function save(Questionnaire $questionnaire): void
    {
        $row = ['definition' => Sql::json($questionnaire->toArray()), 'retention_days' => $questionnaire->retentionDays, 'updated_at' => $this->clock->now()];
        $types = ['definition' => Types::TEXT, 'retention_days' => Types::INTEGER, 'updated_at' => Types::DATETIME_UTC_IMMUTABLE];
        $key = ['questionnaire_key' => $questionnaire->key];

        try {
            if ($this->exists($questionnaire->key)) {
                $this->connection->update(SurveyTables::QUESTIONNAIRES, $row, $key, $types);
            } else {
                $this->connection->insert(SurveyTables::QUESTIONNAIRES, [...$key, ...$row], $types);
            }
        } catch (UniqueConstraintViolationException) {
            // Saved by another request in the meantime: this one replaces it, as a later save does.
            $this->connection->update(SurveyTables::QUESTIONNAIRES, $row, $key, $types);
        }
    }

    #[Override]
    public function find(string $key): ?Questionnaire
    {
        $definition = $this->connection->fetchOne('SELECT definition FROM '.SurveyTables::QUESTIONNAIRES.' WHERE questionnaire_key = ?', [$key]);

        return false === $definition ? null : Questionnaire::fromArray(Sql::decode($definition));
    }

    #[Override]
    public function keys(): array
    {
        return array_map(Column::string(...), $this->connection->fetchFirstColumn('SELECT questionnaire_key FROM '.SurveyTables::QUESTIONNAIRES.' ORDER BY questionnaire_key'));
    }

    #[Override]
    public function retentions(): array
    {
        $retentions = [];

        foreach ($this->connection->fetchAllNumeric('SELECT questionnaire_key, retention_days FROM '.SurveyTables::QUESTIONNAIRES.' WHERE retention_days IS NOT NULL ORDER BY questionnaire_key') as [$key, $days]) {
            $retentions[Column::string($key)] = Column::int($days);
        }

        return $retentions;
    }

    #[Override]
    public function remove(string $key): bool
    {
        return $this->connection->transactional(static function (Connection $connection) use ($key): bool {
            $connection->executeStatement('DELETE FROM '.SurveyTables::ADDENDA.' WHERE response_id IN (SELECT id FROM '.SurveyTables::RESPONSES.' WHERE questionnaire = ?)', [$key]);

            foreach ([SurveyTables::RESPONSES, SurveyTables::QUOTAS, SurveyTables::PARTICIPATIONS] as $table) {
                $connection->delete($table, ['questionnaire' => $key]);
            }

            return 1 === (int) $connection->delete(SurveyTables::QUESTIONNAIRES, ['questionnaire_key' => $key]);
        });
    }

    private function exists(string $key): bool
    {
        return false !== $this->connection->fetchOne('SELECT 1 FROM '.SurveyTables::QUESTIONNAIRES.' WHERE questionnaire_key = ?', [$key]);
    }
}
