<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application;

use Override;
use Phil\PrivacyBundle\Application\Contract\RetentionScheduleSource;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Retention\AccessResponse;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Retention\Mechanism;
use Phil\PrivacyBundle\Domain\Retention\Processing;
use Phil\PrivacyBundle\Domain\Retention\Processor;
use Phil\PrivacyBundle\Domain\Retention\RetentionPeriod;
use Phil\PrivacyBundle\Domain\Retention\RetentionRule;
use Phil\PrivacyBundle\Domain\Retention\RetentionSchedule;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;

/**
 * The retention schedule written in the configuration, under `phil_privacy.retention.rules`.
 *
 * A period is an ISO 8601 duration (`P90D`), `indefinite`, or a map with one key: `until_event` or `not_yet_fixed`.
 * The keys `purpose`, `recipients`, `processors` and `location` are what the register of processing says of the store.
 *
 * @phpstan-type RuleConfig array{store: string, categories: list<string>, period: mixed, mechanism: string, erasure: string, access: string, purpose?: string|null, recipients?: list<string>, processors?: list<array{name: string, location: string}>, location?: string|null}
 */
final readonly class ConfiguredRetentionSchedule implements RetentionScheduleSource
{
    private RetentionSchedule $schedule;

    /**
     * @param array<array-key, RuleConfig> $rules by rule identifier
     */
    public function __construct(array $rules)
    {
        $lines = [];

        foreach ($rules as $id => $rule) {
            $lines[] = new RetentionRule(
                (string) $id,
                $rule['store'],
                array_map(DataCategory::fromString(...), $rule['categories']),
                $this->period($rule['period']),
                Mechanism::from($rule['mechanism']),
                ErasureEffect::from($rule['erasure']),
                AccessResponse::from($rule['access']),
                new Processing(
                    $rule['purpose'] ?? null,
                    $rule['recipients'] ?? [],
                    array_map(static fn (array $processor): Processor => new Processor($processor['name'], $processor['location']), $rule['processors'] ?? []),
                    $rule['location'] ?? null,
                ),
            );
        }

        $this->schedule = new RetentionSchedule($lines);
    }

    #[Override]
    public function load(): RetentionSchedule
    {
        return $this->schedule;
    }

    private function period(mixed $period): RetentionPeriod
    {
        if ('indefinite' === $period) {
            return RetentionPeriod::indefinite();
        }

        if (\is_string($period)) {
            return RetentionPeriod::fixed($period);
        }

        if (\is_array($period) && \is_string($period['until_event'] ?? null)) {
            return RetentionPeriod::untilEvent($period['until_event']);
        }

        if (\is_array($period) && \is_string($period['not_yet_fixed'] ?? null)) {
            return RetentionPeriod::notYetFixed($period['not_yet_fixed']);
        }

        throw InvalidPrivacyValue::because('A retention period is an ISO 8601 duration, "indefinite", or a map with "until_event" or "not_yet_fixed".');
    }
}
