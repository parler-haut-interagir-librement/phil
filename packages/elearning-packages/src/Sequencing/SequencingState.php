<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

use Phil\ElearningPackages\Exception\SequencingRefused;
use Phil\ElearningPackages\Runtime\Duration;
use Phil\ElearningPackages\Sequencing\Prerequisite\Prerequisites;

/**
 * The state of a sequencing session, what a platform keeps between two requests of a learner: the current activity,
 * the activity suspended by `suspendAll`, the seed that makes the selection and the reordering of children
 * reproducible, what is known of each activity, and the shared global objectives. Immutable: the sequencer returns a
 * new state. `toArray()` gives it as plain values, ready for JSON; `fromArray()` reads them back, checked against the
 * activity tree.
 *
 * @phpstan-type ObjectiveRecord array{satisfied: ?bool, measure: ?float}
 * @phpstan-type ActivityRecord array{attempts: int, active: bool, suspended: bool, completed: ?bool, duration: ?int, lesson: ?string, objectives: array<string, ObjectiveRecord>, order: ?list<string>}
 * @phpstan-type StateArray array{version: int, seed: int, current: ?string, suspended: ?string, activities: array<string, ActivityRecord>, globals: array<string, ObjectiveRecord>}
 */
final readonly class SequencingState
{
    public const int VERSION = 1;

    public const int MAX_GLOBALS = 10_000;

    private const int MAX_ATTEMPTS = 1_000_000_000;

    /**
     * @param array<string, ActivityRecord>  $activities
     * @param array<string, ObjectiveRecord> $globals
     */
    private function __construct(
        public int $seed,
        public ?string $current = null,
        public ?string $suspendedActivity = null,
        private array $activities = [],
        private array $globals = [],
    ) {
    }

    /**
     * The state of a session not begun: nothing attempted, no global objective known.
     *
     * @param ?int $seed the seed of the selection and the reordering of children; drawn when null
     */
    public static function begin(?int $seed = null): self
    {
        return new self($seed ?? random_int(0, \PHP_INT_MAX));
    }

    /**
     * The state as plain values: only the activities and the global objectives something is known of.
     *
     * @return StateArray
     */
    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'seed' => $this->seed,
            'current' => $this->current,
            'suspended' => $this->suspendedActivity,
            'activities' => $this->activities,
            'globals' => $this->globals,
        ];
    }

    /**
     * Reads back what `toArray()` gave, checking each value and that each activity named is one of the tree.
     *
     * @param array<mixed> $data
     *
     * @throws SequencingRefused when the data is not a state of this version, names an activity the tree does not
     *                           hold, or holds a value out of its bounds
     */
    public static function fromArray(array $data, ActivityTree $tree): self
    {
        if (self::VERSION !== ($data['version'] ?? null) || !\is_int($data['seed'] ?? null)) {
            throw SequencingRefused::because(\sprintf('A sequencing state is of version %d and holds an integer seed.', self::VERSION));
        }

        $activities = $data['activities'] ?? null;
        $globals = $data['globals'] ?? null;

        if (!\is_array($activities) || !\is_array($globals) || \count($globals) > self::MAX_GLOBALS) {
            throw SequencingRefused::because(\sprintf('A sequencing state holds its activities and at most %d global objectives.', self::MAX_GLOBALS));
        }

        $records = [];

        foreach ($activities as $identifier => $record) {
            $identifier = (string) $identifier;
            $records[$identifier] = self::record($tree, $identifier, $record);
        }

        $objectives = [];

        foreach ($globals as $identifier => $objective) {
            $objectives[(string) $identifier] = self::objective($objective);
        }

        return new self(
            $data['seed'],
            self::identifier($tree, $data['current'] ?? null),
            self::identifier($tree, $data['suspended'] ?? null),
            $records,
            $objectives,
        );
    }

    /**
     * @param StateArray $data
     *
     * @internal the sequencer rebuilds the states it computed; a stored state is read by `fromArray()`
     */
    public static function trusted(array $data): self
    {
        return new self($data['seed'], $data['current'], $data['suspended'], $data['activities'], $data['globals']);
    }

    public function activity(string $identifier): ActivityStatus
    {
        $record = $this->activities[$identifier] ?? null;

        if (null === $record) {
            return new ActivityStatus();
        }

        return new ActivityStatus(
            $record['attempts'],
            $record['active'],
            $record['suspended'],
            $record['completed'],
            null === $record['duration'] ? null : Duration::ofCentiseconds($record['duration']),
            array_map(static fn (array $objective): ObjectiveStatus => new ObjectiveStatus($objective['satisfied'], $objective['measure']), $record['objectives']),
            $record['lesson'],
        );
    }

    /**
     * What is known of the global objective of that identifier.
     */
    public function global(string $identifier): ObjectiveStatus
    {
        $objective = $this->globals[$identifier] ?? null;

        return null === $objective ? new ObjectiveStatus() : new ObjectiveStatus($objective['satisfied'], $objective['measure']);
    }

    /**
     * @return array<string, ObjectiveStatus> the global objectives something is known of, by identifier
     */
    public function globals(): array
    {
        return array_map(static fn (array $objective): ObjectiveStatus => new ObjectiveStatus($objective['satisfied'], $objective['measure']), $this->globals);
    }

    private static function identifier(ActivityTree $tree, mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value) || !$tree->has($value)) {
            throw SequencingRefused::because('A sequencing state names an activity the tree does not hold.');
        }

        return $value;
    }

    /**
     * @return ActivityRecord
     */
    private static function record(ActivityTree $tree, string $identifier, mixed $record): array
    {
        if (!$tree->has($identifier) || !\is_array($record)) {
            throw SequencingRefused::because('A sequencing state names an activity the tree does not hold.');
        }

        $attempts = $record['attempts'] ?? null;
        $duration = $record['duration'] ?? null;
        $lesson = $record['lesson'] ?? null;
        $objectives = $record['objectives'] ?? null;
        $order = $record['order'] ?? null;

        if (!\is_int($attempts) || $attempts < 0 || $attempts > self::MAX_ATTEMPTS
            || !\is_bool($record['active'] ?? null) || !\is_bool($record['suspended'] ?? null)
            || !self::isOptionalBool($record['completed'] ?? null)
            || (null !== $duration && (!\is_int($duration) || $duration < 0 || $duration > Duration::MAX_CENTISECONDS))
            || (null !== $lesson && !\in_array($lesson, Prerequisites::STATUSES, true))
            || !\is_array($objectives) || \count($objectives) > SequencingReader::MAX_OBJECTIVES) {
            throw SequencingRefused::because(\sprintf('The state of the activity "%s" holds a value out of its bounds.', $identifier));
        }

        $children = array_map(static fn (Activity $child): string => $child->identifier, $tree->get($identifier)->children);

        $strings = \is_array($order) ? array_values(array_filter($order, \is_string(...))) : [];

        if (null !== $order && (!\is_array($order) || !array_is_list($order) || \count($strings) !== \count($order) || \count($order) !== \count(array_intersect($children, $strings)))) {
            throw SequencingRefused::because(\sprintf('The state of the activity "%s" orders children it does not have.', $identifier));
        }

        $completed = $record['completed'] ?? null;

        return [
            'attempts' => $attempts,
            'active' => $record['active'],
            'suspended' => $record['suspended'],
            'completed' => \is_bool($completed) ? $completed : null,
            'duration' => $duration,
            'lesson' => $lesson,
            'objectives' => array_map(self::objective(...), array_combine(array_map(strval(...), array_keys($objectives)), $objectives)),
            'order' => null === $order ? null : $strings,
        ];
    }

    /**
     * @return ObjectiveRecord
     */
    private static function objective(mixed $objective): array
    {
        $measure = \is_array($objective) ? ($objective['measure'] ?? null) : null;

        if (\is_int($measure)) {
            $measure = (float) $measure;
        }

        if (!\is_array($objective) || !self::isOptionalBool($objective['satisfied'] ?? null)
            || (null !== $measure && (!\is_float($measure) || $measure < -1.0 || $measure > 1.0))) {
            throw SequencingRefused::because('An objective of a sequencing state holds a value out of its bounds.');
        }

        $satisfied = $objective['satisfied'] ?? null;

        return ['satisfied' => \is_bool($satisfied) ? $satisfied : null, 'measure' => $measure];
    }

    private static function isOptionalBool(mixed $value): bool
    {
        return null === $value || \is_bool($value);
    }
}
