<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Override;
use Phil\ProposalReview\Aggregation;
use Phil\ProposalReview\Campaign;
use Phil\ProposalReview\Contract\CampaignStore;
use Phil\ProposalReview\Criterion;
use Phil\ProposalReview\Exception\InvalidInput;
use Phil\ProposalReview\Phase;
use Phil\ProposalReview\PhaseWindow;
use Phil\ProposalReview\ReviewPolicy;
use Phil\ProposalReview\Schedule;
use Phil\ProposalReview\Visibility;
use UnexpectedValueException;

/**
 * The campaigns in a table of a Doctrine DBAL connection, one row each: the schedule, the criteria and the policy as
 * one JSON document, the last phase recorded by its name.
 */
final readonly class DbalCampaignStore implements CampaignStore
{
    /** @var non-empty-string */
    private string $table;

    /**
     * @throws InvalidInput when the prefix is not one
     */
    public function __construct(
        private Connection $connection,
        string $prefix = Tables::PREFIX,
    ) {
        $this->table = Tables::prefix($prefix).'campaign';
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
                    Tables::identifierColumn('id', $platform),
                    Tables::textColumn('definition'),
                    Tables::wordColumn('observed_phase', 16, $platform),
                )
                ->setPrimaryKeyConstraint(Tables::primaryKey(['id']))
                ->create(),
        )->create();
    }

    #[Override]
    public function get(string $id): ?Campaign
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT id, definition, observed_phase FROM %s WHERE id = ?', $this->table), [$id]);

        return false === $row || $row['id'] !== $id ? null : $this->campaign($id, $row);
    }

    #[Override]
    public function save(Campaign $campaign): void
    {
        $row = [
            'id' => Tables::identifier($campaign->id),
            'definition' => Tables::encode([
                'schedule' => array_map(static fn (PhaseWindow $window): array => [
                    'phase' => $window->phase->value,
                    'start' => Tables::instant($window->start),
                    'end' => Tables::instant($window->end),
                ], $campaign->schedule->windows),
                'criteria' => array_map(static fn (Criterion $criterion): array => [
                    'id' => Tables::identifier($criterion->id),
                    'weight' => $criterion->weight,
                    'min' => $criterion->min,
                    'max' => $criterion->max,
                ], $campaign->criteria),
                'policy' => [
                    'blind' => $campaign->policy->blind,
                    'minReviews' => $campaign->policy->minReviews,
                    'maxReviews' => $campaign->policy->maxReviews,
                    'aggregation' => $campaign->policy->aggregation->value,
                    'visibility' => $campaign->policy->visibility->value,
                    'assignedOnly' => $campaign->policy->assignedOnly,
                ],
            ]),
            'observed_phase' => $campaign->observedPhase->value,
        ];

        $this->connection->transactional(function (Connection $connection) use ($row): void {
            $connection->executeStatement(\sprintf('DELETE FROM %s WHERE id = ?', $this->table), [$row['id']]);
            $connection->insert($this->table, $row);
        });
    }

    /**
     * @param array<string, mixed> $row
     */
    private function campaign(string $id, array $row): Campaign
    {
        $definition = Tables::decode($row['definition'], 'definition of a campaign');
        $policy = Tables::array($definition['policy'] ?? null, 'policy of a campaign');
        $maxReviews = $policy['maxReviews'] ?? null;

        return new Campaign(
            $id,
            new Schedule(Tables::each($definition['schedule'] ?? null, 'schedule of a campaign', static function (mixed $value): PhaseWindow {
                $window = Tables::array($value, 'window of a schedule');

                return new PhaseWindow(
                    self::phase($window['phase'] ?? null),
                    Tables::readInstant($window['start'] ?? null, 'start of a phase'),
                    Tables::readInstant($window['end'] ?? null, 'end of a phase'),
                );
            })),
            Tables::each($definition['criteria'] ?? null, 'criteria of a campaign', static function (mixed $value): Criterion {
                $criterion = Tables::array($value, 'criterion of a campaign');

                return new Criterion(
                    Tables::text($criterion['id'] ?? null, 'identifier of a criterion'),
                    Tables::integer($criterion['weight'] ?? null, 'weight of a criterion'),
                    Tables::integer($criterion['min'] ?? null, 'lowest score of a criterion'),
                    Tables::integer($criterion['max'] ?? null, 'highest score of a criterion'),
                );
            }),
            new ReviewPolicy(
                Tables::boolean($policy['blind'] ?? null, 'blindness of a policy'),
                Tables::integer($policy['minReviews'] ?? null, 'fewest reviews of a policy'),
                null === $maxReviews ? null : Tables::integer($maxReviews, 'most reviews of a policy'),
                Aggregation::tryFrom(Tables::text($policy['aggregation'] ?? null, 'aggregation of a policy')) ?? throw new UnexpectedValueException('The stored aggregation of a policy is unknown.'),
                Visibility::tryFrom(Tables::text($policy['visibility'] ?? null, 'visibility of a policy')) ?? throw new UnexpectedValueException('The stored visibility of a policy is unknown.'),
                Tables::boolean($policy['assignedOnly'] ?? null, 'assigned reviewers rule of a policy'),
            ),
            self::phase($row['observed_phase']),
        );
    }

    private static function phase(mixed $value): Phase
    {
        return Phase::tryFrom(Tables::text($value, 'phase')) ?? throw new UnexpectedValueException('The stored phase is unknown.');
    }
}
