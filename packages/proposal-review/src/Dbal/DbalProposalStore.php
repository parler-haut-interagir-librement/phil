<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Override;
use Phil\ProposalReview\ConflictOfInterest;
use Phil\ProposalReview\Contract\ProposalStore;
use Phil\ProposalReview\Exception\InvalidInput;
use Phil\ProposalReview\Proposal;
use UnexpectedValueException;

/**
 * The proposals, their assignments and the conflicts declared, in three tables of a Doctrine DBAL connection: a
 * proposal's authors as a JSON list, an assignment as a row keyed by campaign, proposal and reviewer, a conflict as a
 * row keyed by campaign, reviewer, kind ("proposal" or "author") and target.
 */
final readonly class DbalProposalStore implements ProposalStore
{
    private const string BY_PROPOSAL = 'proposal';

    private const string BY_AUTHOR = 'author';

    /** @var non-empty-string */
    private string $proposals;

    /** @var non-empty-string */
    private string $assignments;

    /** @var non-empty-string */
    private string $conflicts;

    /**
     * @throws InvalidInput when the prefix is not one
     */
    public function __construct(
        private Connection $connection,
        string $prefix = Tables::PREFIX,
    ) {
        $prefix = Tables::prefix($prefix);
        $this->proposals = $prefix.'proposal';
        $this->assignments = $prefix.'assignment';
        $this->conflicts = $prefix.'conflict';
    }

    /**
     * Adds the tables a schema lacks: one a migration builds, or the one the ORM generates.
     */
    public function configureSchema(Schema $schema): Schema
    {
        $platform = $this->connection->getDatabasePlatform();
        $tables = [];

        if (!$schema->hasTable($this->proposals)) {
            $tables[] = Table::editor()
                ->setUnquotedName($this->proposals)
                ->setColumns(
                    Tables::identifierColumn('campaign', $platform),
                    Tables::identifierColumn('id', $platform),
                    Tables::textColumn('authors'),
                )
                ->setPrimaryKeyConstraint(Tables::primaryKey(['campaign', 'id']))
                ->create();
        }

        if (!$schema->hasTable($this->assignments)) {
            $tables[] = Table::editor()
                ->setUnquotedName($this->assignments)
                ->setColumns(
                    Tables::identifierColumn('campaign', $platform),
                    Tables::identifierColumn('proposal', $platform),
                    Tables::identifierColumn('reviewer', $platform),
                )
                ->setPrimaryKeyConstraint(Tables::primaryKey(['campaign', 'proposal', 'reviewer']))
                ->create();
        }

        if (!$schema->hasTable($this->conflicts)) {
            $tables[] = Table::editor()
                ->setUnquotedName($this->conflicts)
                ->setColumns(
                    Tables::identifierColumn('campaign', $platform),
                    Tables::identifierColumn('reviewer', $platform),
                    Tables::wordColumn('kind', 8, $platform),
                    Tables::identifierColumn('target', $platform),
                )
                ->setPrimaryKeyConstraint(Tables::primaryKey(['campaign', 'reviewer', 'kind', 'target']))
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
    public function get(string $campaign, string $id): ?Proposal
    {
        if (!Tables::isStorable($campaign) || !Tables::isStorable($id)) {
            return null;
        }

        $row = $this->connection->fetchAssociative(\sprintf('SELECT campaign, id, authors FROM %s WHERE campaign = ? AND id = ?', $this->proposals), [$campaign, $id]);

        return false === $row ? null : $this->proposal($row);
    }

    #[Override]
    public function ofCampaign(string $campaign): array
    {
        if (!Tables::isStorable($campaign)) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(\sprintf('SELECT campaign, id, authors FROM %s WHERE campaign = ?', $this->proposals), [$campaign]);

        return Tables::sortBy(array_map($this->proposal(...), $rows), static fn (Proposal $proposal): string => $proposal->id);
    }

    #[Override]
    public function save(Proposal $proposal): void
    {
        $row = [
            'campaign' => Tables::identifier($proposal->campaign),
            'id' => Tables::identifier($proposal->id),
            'authors' => Tables::encode(array_map(Tables::identifier(...), $proposal->authors)),
        ];

        $this->connection->transactional(function (Connection $connection) use ($row): void {
            $connection->executeStatement(\sprintf('DELETE FROM %s WHERE campaign = ? AND id = ?', $this->proposals), [$row['campaign'], $row['id']]);
            $connection->insert($this->proposals, $row);
        });
    }

    #[Override]
    public function assign(string $campaign, string $proposal, string $reviewer): void
    {
        $this->insertOnce($this->assignments, [
            'campaign' => Tables::identifier($campaign),
            'proposal' => Tables::identifier($proposal),
            'reviewer' => Tables::identifier($reviewer),
        ]);
    }

    #[Override]
    public function reviewersOf(string $campaign, string $proposal): array
    {
        if (!Tables::isStorable($campaign) || !Tables::isStorable($proposal)) {
            return [];
        }

        $reviewers = array_map(
            static fn (mixed $reviewer): string => Tables::text($reviewer, 'reviewer of an assignment'),
            $this->connection->fetchFirstColumn(\sprintf('SELECT reviewer FROM %s WHERE campaign = ? AND proposal = ?', $this->assignments), [$campaign, $proposal]),
        );
        sort($reviewers, \SORT_STRING);

        return $reviewers;
    }

    #[Override]
    public function assignmentCounts(string $campaign): array
    {
        if (!Tables::isStorable($campaign)) {
            return [];
        }

        $counts = [];

        foreach ($this->connection->fetchAllNumeric(\sprintf('SELECT reviewer, COUNT(*) FROM %s WHERE campaign = ? GROUP BY reviewer', $this->assignments), [$campaign]) as [$reviewer, $count]) {
            $counts[Tables::text($reviewer, 'reviewer of an assignment')] = Tables::integer($count, 'number of assignments');
        }

        ksort($counts, \SORT_STRING);

        return $counts;
    }

    #[Override]
    public function declareConflict(ConflictOfInterest $conflict): void
    {
        $this->insertOnce($this->conflicts, [
            'campaign' => Tables::identifier($conflict->campaign),
            'reviewer' => Tables::identifier($conflict->reviewer),
            'kind' => null === $conflict->proposal ? self::BY_AUTHOR : self::BY_PROPOSAL,
            'target' => Tables::identifier($conflict->proposal ?? $conflict->author ?? ''),
        ]);
    }

    #[Override]
    public function conflictsOf(string $campaign, string $reviewer): array
    {
        if (!Tables::isStorable($campaign) || !Tables::isStorable($reviewer)) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(\sprintf('SELECT kind, target FROM %s WHERE campaign = ? AND reviewer = ?', $this->conflicts), [$campaign, $reviewer]);
        $conflicts = array_map(static function (array $row) use ($campaign, $reviewer): ConflictOfInterest {
            $target = Tables::text($row['target'], 'target of a conflict');

            return match (Tables::text($row['kind'], 'kind of a conflict')) {
                self::BY_PROPOSAL => ConflictOfInterest::withProposal($campaign, $reviewer, $target),
                self::BY_AUTHOR => ConflictOfInterest::withAuthor($campaign, $reviewer, $target),
                default => throw new UnexpectedValueException('The stored kind of a conflict is unknown.'),
            };
        }, $rows);

        return Tables::sortBy($conflicts, static fn (ConflictOfInterest $conflict): string => (null === $conflict->proposal ? 'b' : 'a').($conflict->proposal ?? $conflict->author));
    }

    /**
     * Inserts a row its primary key keeps once: a row already there, or inserted at the same time, leaves it as is.
     *
     * @param non-empty-string      $table
     * @param array<string, string> $row
     */
    private function insertOnce(string $table, array $row): void
    {
        $conditions = implode(' AND ', array_map(static fn (string $column): string => $column.' = ?', array_keys($row)));

        if (false !== $this->connection->fetchOne(\sprintf('SELECT 1 FROM %s WHERE %s', $table, $conditions), array_values($row))) {
            return;
        }

        try {
            // A savepoint inside the caller's transaction, which a refused insert would otherwise abort on PostgreSQL.
            $this->connection->transactional(static fn (Connection $connection): int|string => $connection->insert($table, $row));
        } catch (UniqueConstraintViolationException) {
            // Kept by another request between the read and the insert: the row is there, as asked.
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function proposal(array $row): Proposal
    {
        return new Proposal(
            Tables::text($row['campaign'], 'campaign of a proposal'),
            Tables::text($row['id'], 'identifier of a proposal'),
            Tables::each(Tables::decode($row['authors'], 'authors of a proposal'), 'authors of a proposal', static fn (mixed $author): string => Tables::text($author, 'author of a proposal')),
        );
    }
}
