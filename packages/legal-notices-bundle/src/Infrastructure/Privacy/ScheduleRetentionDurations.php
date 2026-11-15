<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Infrastructure\Privacy;

use Override;
use Phil\LegalNotices\Contract\RetentionDurations;
use Phil\LegalNotices\RetentionDuration;
use Phil\LegalNotices\RetentionKind;
use Phil\PrivacyBundle\Application\Contract\RetentionScheduleSource;
use Phil\PrivacyBundle\Domain\Retention\RetentionRule;

/**
 * The retention periods of the schedule of `phil/privacy-bundle`, read when a page asks for them: each rule that holds
 * personal information — or every rule —, what it keeps named by the labels of the configuration, else by its store.
 * The event of a period until an event is shown as the schedule names it; the other notes stay internal.
 */
final readonly class ScheduleRetentionDurations implements RetentionDurations
{
    /**
     * @param array<string, array<string, string>> $labels what each rule keeps, by rule then locale
     */
    public function __construct(
        private RetentionScheduleSource $source,
        private array $labels = [],
        private bool $personalOnly = true,
    ) {
    }

    #[Override]
    public function durations(): array
    {
        $durations = [];

        foreach ($this->source->load()->rules() as $rule) {
            if (!$this->personalOnly || $rule->holdsPersonalInformation()) {
                $durations[] = $this->duration($rule);
            }
        }

        return $durations;
    }

    private function duration(RetentionRule $rule): RetentionDuration
    {
        $kind = RetentionKind::from($rule->period->kind->value);

        return new RetentionDuration(
            $rule->id,
            $rule->store,
            $kind,
            $rule->period->duration,
            RetentionKind::UntilEvent === $kind ? $rule->period->note : null,
            $this->labels[$rule->id] ?? [],
        );
    }
}
