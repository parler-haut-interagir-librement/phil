<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\ProposalReview\Contract\VoteLog;
use Phil\ProposalReview\Exception\InvalidInput;
use Phil\ProposalReview\Pairwise\Vote;

/**
 * The pairwise votes in a table of a Doctrine DBAL connection, keyed by campaign and number. A vote takes the number
 * after the campaign's last; when another request took it first, the primary key refuses the row and the vote takes the
 * next number, a bounded number of times.
 */
final readonly class DbalVoteLog implements VoteLog
{
    /** How many times a vote tries the next number before the store gives up. */
    public const int MAX_ATTEMPTS = 8;

    /** @var non-empty-string */
    private string $table;

    /**
     * @throws InvalidInput when the prefix is not one
     */
    public function __construct(
        private Connection $connection,
        string $prefix = Tables::PREFIX,
    ) {
        $this->table = Tables::prefix($prefix).'vote';
    }

    /**
     * Adds the table to a schema that lacks it: one a migration builds, or the one the ORM generates.
     */
    public function configureSchema(Schema $schema): Schema
    {
        if ($schema->hasTable($this->table)) {
            return $schema;
        }

        $platform = $this->connection->getDatabasePlatform();

        return $schema->edit()->addTable(
            Table::editor()
                ->setUnquotedName($this->table)
                ->setColumns(
                    Tables::identifierColumn('campaign', $platform),
                    Column::editor()->setUnquotedName('sequence')->setTypeName(Types::BIGINT)->create(),
                    Tables::identifierColumn('voter', $platform),
                    Tables::identifierColumn('winner', $platform),
                    Tables::identifierColumn('loser', $platform),
                    Tables::instantColumn('cast_at', $platform),
                )
                ->setPrimaryKeyConstraint(Tables::primaryKey(['campaign', 'sequence']))
                ->create(),
        )->create();
    }

    /**
     * @throws UniqueConstraintViolationException when every number tried was taken by another request
     */
    #[Override]
    public function append(string $campaign, string $voter, string $winner, string $loser, DateTimeImmutable $castAt): Vote
    {
        $row = [
            'campaign' => Tables::identifier($campaign),
            'voter' => Tables::identifier($voter),
            'winner' => Tables::identifier($winner),
            'loser' => Tables::identifier($loser),
            'cast_at' => Tables::instant($castAt),
        ];

        $table = $this->table;

        for ($attempt = 1;; ++$attempt) {
            $last = $this->connection->fetchOne(\sprintf('SELECT MAX(sequence) FROM %s WHERE campaign = ?', $this->table), [$campaign]);
            $vote = new Vote($campaign, null === $last ? 1 : Tables::integer($last, 'number of a vote') + 1, $voter, $winner, $loser, $castAt);

            try {
                // A savepoint inside the caller's transaction, which a refused insert would otherwise abort on PostgreSQL.
                $this->connection->transactional(static fn (Connection $connection): int|string => $connection->insert($table, [...$row, 'sequence' => $vote->sequence]));

                return $vote;
            } catch (UniqueConstraintViolationException $taken) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $taken;
                }
            }
        }
    }

    #[Override]
    public function ofCampaign(string $campaign): array
    {
        if (!Tables::isStorable($campaign)) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(\sprintf('SELECT campaign, sequence, voter, winner, loser, cast_at FROM %s WHERE campaign = ? ORDER BY sequence', $this->table), [$campaign]);

        return array_map(static fn (array $row): Vote => new Vote(
            Tables::text($row['campaign'], 'campaign of a vote'),
            Tables::integer($row['sequence'], 'number of a vote'),
            Tables::text($row['voter'], 'voter of a vote'),
            Tables::text($row['winner'], 'winner of a vote'),
            Tables::text($row['loser'], 'loser of a vote'),
            Tables::readInstant($row['cast_at'], 'instant of a vote'),
        ), $rows);
    }
}
