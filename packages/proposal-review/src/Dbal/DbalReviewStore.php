<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Override;
use Phil\ProposalReview\Contract\ReviewStore;
use Phil\ProposalReview\Decision;
use Phil\ProposalReview\DecisionRecord;
use Phil\ProposalReview\Exception\InvalidInput;
use Phil\ProposalReview\Review;
use Phil\ProposalReview\Score;
use UnexpectedValueException;

/**
 * The reviews and decisions in two tables of a Doctrine DBAL connection: a review keyed by campaign, proposal and
 * reviewer, its scores as a JSON list; a decision keyed by campaign and proposal, pending while it has no instant of
 * application.
 */
final readonly class DbalReviewStore implements ReviewStore
{
    /** @var non-empty-string */
    private string $reviews;

    /** @var non-empty-string */
    private string $decisions;

    /**
     * @throws InvalidInput when the prefix is not one
     */
    public function __construct(
        private Connection $connection,
        string $prefix = Tables::PREFIX,
    ) {
        $prefix = Tables::prefix($prefix);
        $this->reviews = $prefix.'review';
        $this->decisions = $prefix.'decision';
    }

    /**
     * Adds the tables a schema lacks: one a migration builds, or the one the ORM generates.
     */
    public function configureSchema(Schema $schema): Schema
    {
        $platform = $this->connection->getDatabasePlatform();
        $tables = [];

        if (!$schema->hasTable($this->reviews)) {
            $tables[] = Table::editor()
                ->setUnquotedName($this->reviews)
                ->setColumns(
                    Tables::identifierColumn('campaign', $platform),
                    Tables::identifierColumn('proposal', $platform),
                    Tables::identifierColumn('reviewer', $platform),
                    Tables::textColumn('scores'),
                    Tables::instantColumn('submitted_at', $platform),
                )
                ->setPrimaryKeyConstraint(Tables::primaryKey(['campaign', 'proposal', 'reviewer']))
                ->create();
        }

        if (!$schema->hasTable($this->decisions)) {
            $tables[] = Table::editor()
                ->setUnquotedName($this->decisions)
                ->setColumns(
                    Tables::identifierColumn('campaign', $platform),
                    Tables::identifierColumn('proposal', $platform),
                    Tables::wordColumn('decision', 16, $platform),
                    Tables::identifierColumn('recorded_by', $platform),
                    Tables::instantColumn('recorded_at', $platform),
                    Tables::instantColumn('applied_at', $platform, notNull: false),
                )
                ->setPrimaryKeyConstraint(Tables::primaryKey(['campaign', 'proposal']))
                ->create();
        }

        if ([] === $tables) {
            return $schema;
        }

        $editor = $schema->edit();

        foreach ($tables as $table) {
            $editor->addTable($table);
        }

        return $editor->create();
    }

    #[Override]
    public function save(Review $review): void
    {
        $row = [
            'campaign' => Tables::identifier($review->campaign),
            'proposal' => Tables::identifier($review->proposal),
            'reviewer' => Tables::identifier($review->reviewer),
            'scores' => Tables::encode(array_map(static fn (Score $score): array => [
                'criterion' => Tables::identifier($score->criterion),
                'value' => $score->value,
            ], $review->scores)),
            'submitted_at' => Tables::instant($review->submittedAt),
        ];

        $this->connection->transactional(function (Connection $connection) use ($row): void {
            $connection->executeStatement(\sprintf('DELETE FROM %s WHERE campaign = ? AND proposal = ? AND reviewer = ?', $this->reviews), [$row['campaign'], $row['proposal'], $row['reviewer']]);
            $connection->insert($this->reviews, $row);
        });
    }

    #[Override]
    public function ofProposal(string $campaign, string $proposal): array
    {
        if (!Tables::isStorable($campaign) || !Tables::isStorable($proposal)) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(\sprintf('SELECT campaign, proposal, reviewer, scores, submitted_at FROM %s WHERE campaign = ? AND proposal = ?', $this->reviews), [$campaign, $proposal]);

        return Tables::sortBy(array_map($this->review(...), $rows), static fn (Review $review): string => $review->reviewer);
    }

    #[Override]
    public function saveDecision(DecisionRecord $decision): void
    {
        $row = [
            'campaign' => Tables::identifier($decision->campaign),
            'proposal' => Tables::identifier($decision->proposal),
            'decision' => $decision->decision->value,
            'recorded_by' => Tables::identifier($decision->recordedBy),
            'recorded_at' => Tables::instant($decision->recordedAt),
            'applied_at' => $decision->appliedAt instanceof DateTimeImmutable ? Tables::instant($decision->appliedAt) : null,
        ];

        $this->connection->transactional(function (Connection $connection) use ($row): void {
            $connection->executeStatement(\sprintf('DELETE FROM %s WHERE campaign = ? AND proposal = ?', $this->decisions), [$row['campaign'], $row['proposal']]);
            $connection->insert($this->decisions, $row);
        });
    }

    #[Override]
    public function decisions(string $campaign): array
    {
        if (!Tables::isStorable($campaign)) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(\sprintf('SELECT campaign, proposal, decision, recorded_by, recorded_at, applied_at FROM %s WHERE campaign = ?', $this->decisions), [$campaign]);

        return Tables::sortBy(array_map($this->decision(...), $rows), static fn (DecisionRecord $record): string => $record->proposal);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function review(array $row): Review
    {
        return new Review(
            Tables::text($row['campaign'], 'campaign of a review'),
            Tables::text($row['proposal'], 'proposal of a review'),
            Tables::text($row['reviewer'], 'reviewer of a review'),
            Tables::each(Tables::decode($row['scores'], 'scores of a review'), 'scores of a review', static function (mixed $value): Score {
                $score = Tables::array($value, 'score of a review');

                return new Score(
                    Tables::text($score['criterion'] ?? null, 'criterion of a score'),
                    Tables::integer($score['value'] ?? null, 'value of a score'),
                );
            }),
            Tables::readInstant($row['submitted_at'], 'instant of a review'),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function decision(array $row): DecisionRecord
    {
        return new DecisionRecord(
            Tables::text($row['campaign'], 'campaign of a decision'),
            Tables::text($row['proposal'], 'proposal of a decision'),
            Decision::tryFrom(Tables::text($row['decision'], 'decision')) ?? throw new UnexpectedValueException('The stored decision is unknown.'),
            Tables::text($row['recorded_by'], 'author of a decision'),
            Tables::readInstant($row['recorded_at'], 'instant of a decision'),
            null === $row['applied_at'] ? null : Tables::readInstant($row['applied_at'], 'instant a decision was applied'),
        );
    }
}
