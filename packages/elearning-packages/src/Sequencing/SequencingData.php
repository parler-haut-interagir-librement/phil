<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * The working copy of a sequencing state during one request: what is known of each activity and of the global
 * objectives, read through the maps of each objective and written back through them.
 *
 * @internal built by `SequencingRun` for each request
 *
 * @phpstan-import-type ActivityRecord from SequencingState
 * @phpstan-import-type ObjectiveRecord from SequencingState
 */
final class SequencingData
{
    public const array DEFAULT_RECORD = [
        'attempts' => 0,
        'active' => false,
        'suspended' => false,
        'completed' => null,
        'duration' => null,
        'lesson' => null,
        'objectives' => [],
        'order' => null,
    ];

    /** @var array<string, ActivityRecord> */
    public array $records;

    /** @var array<string, ObjectiveRecord> */
    public array $globals;

    public ?string $current;

    public ?string $suspended;

    public readonly int $seed;

    public function __construct(
        private readonly ActivityTree $tree,
        SequencingState $state,
    ) {
        $data = $state->toArray();
        $this->records = $data['activities'];
        $this->globals = $data['globals'];
        $this->current = $data['current'];
        $this->suspended = $data['suspended'];
        $this->seed = $data['seed'];
    }

    /**
     * @return ActivityRecord
     */
    public function record(string $identifier): array
    {
        return $this->records[$identifier] ?? self::DEFAULT_RECORD;
    }

    /**
     * Whether the objective is satisfied: from the first global objective it reads that knows, else as the activity holds it.
     */
    public function satisfied(Activity $activity, Objective $objective): ?bool
    {
        foreach ($objective->maps as $map) {
            $global = $this->globals[$map->targetObjectiveId]['satisfied'] ?? null;

            if ($map->readSatisfiedStatus && null !== $global) {
                return $global;
            }
        }

        return $this->record($activity->identifier)['objectives'][$objective->key()]['satisfied'] ?? null;
    }

    /**
     * The measure of the objective: from the first global objective it reads that knows, else as the activity holds it.
     */
    public function measure(Activity $activity, Objective $objective): ?float
    {
        foreach ($objective->maps as $map) {
            $global = $this->globals[$map->targetObjectiveId]['measure'] ?? null;

            if ($map->readNormalizedMeasure && null !== $global) {
                return $global;
            }
        }

        return $this->record($activity->identifier)['objectives'][$objective->key()]['measure'] ?? null;
    }

    /**
     * Sets the satisfaction the activity holds, and writes it to the global objectives the objective writes.
     */
    public function setSatisfied(Activity $activity, Objective $objective, ?bool $satisfied): void
    {
        $this->storeLocal($activity, $objective, ['satisfied' => $satisfied, 'measure' => $this->local($activity, $objective)['measure']]);

        foreach ($objective->maps as $map) {
            if ($map->writeSatisfiedStatus && null !== $satisfied) {
                $this->globals[$map->targetObjectiveId] = ['satisfied' => $satisfied] + ($this->globals[$map->targetObjectiveId] ?? ['satisfied' => null, 'measure' => null]);
            }
        }
    }

    /**
     * Sets the measure the activity holds, writes it to the global objectives the objective writes, and decides the
     * satisfaction of an objective other than the primary one satisfied by its measure.
     */
    public function setMeasure(Activity $activity, Objective $objective, ?float $measure): void
    {
        $this->storeLocal($activity, $objective, ['satisfied' => $this->local($activity, $objective)['satisfied'], 'measure' => $measure]);

        foreach ($objective->maps as $map) {
            if ($map->writeNormalizedMeasure && null !== $measure) {
                $this->globals[$map->targetObjectiveId] = ['measure' => $measure] + ($this->globals[$map->targetObjectiveId] ?? ['satisfied' => null, 'measure' => null]);
            }
        }

        if (!$objective->primary && $objective->satisfiedByMeasure && null !== $measure) {
            $this->setSatisfied($activity, $objective, $measure >= $objective->minNormalizedMeasure);
        }
    }

    /**
     * @return ObjectiveRecord
     */
    private function local(Activity $activity, Objective $objective): array
    {
        return $this->record($activity->identifier)['objectives'][$objective->key()] ?? ['satisfied' => null, 'measure' => null];
    }

    /**
     * @param ObjectiveRecord $local
     */
    private function storeLocal(Activity $activity, Objective $objective, array $local): void
    {
        $record = $this->record($activity->identifier);

        if (['satisfied' => null, 'measure' => null] === $local) {
            unset($record['objectives'][$objective->key()]);
        } else {
            $record['objectives'][$objective->key()] = $local;
        }

        $this->records[$activity->identifier] = $record;
    }

    /**
     * The `lesson_status` of SCORM 1.2 of an activity: the one its content last reported, else what its data reads as.
     */
    public function lessonStatus(string $identifier): string
    {
        $record = $this->record($identifier);

        if (null !== $record['lesson']) {
            return $record['lesson'];
        }

        $activity = $this->tree->get($identifier);

        return match (true) {
            true === $this->satisfied($activity, $activity->sequencing->primaryObjective()) => 'passed',
            false === $this->satisfied($activity, $activity->sequencing->primaryObjective()) => 'failed',
            true === $record['completed'] => 'completed',
            false === $record['completed'], $record['attempts'] > 0 => 'incomplete',
            default => 'not attempted',
        };
    }

    /**
     * The state of the working copy, only the activities something is known of kept, in the order of the tree, each in
     * the order of its fields.
     */
    public function state(): SequencingState
    {
        return SequencingState::trusted([
            'version' => SequencingState::VERSION,
            'seed' => $this->seed,
            'current' => $this->current,
            'suspended' => $this->suspended,
            'activities' => $this->records(),
            'globals' => array_map(static fn (array $objective): array => ['satisfied' => $objective['satisfied'], 'measure' => $objective['measure']], $this->globals),
        ]);
    }

    /**
     * @return array<string, ActivityRecord>
     */
    private function records(): array
    {
        $records = [];

        foreach ($this->tree->activities() as $activity) {
            $record = array_replace(self::DEFAULT_RECORD, $this->records[$activity->identifier] ?? []);

            if (self::DEFAULT_RECORD !== $record) {
                $records[$activity->identifier] = $record;
            }
        }

        return $records;
    }
}
