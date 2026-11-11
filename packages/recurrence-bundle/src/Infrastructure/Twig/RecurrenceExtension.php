<?php

declare(strict_types=1);

namespace Phil\RecurrenceBundle\Infrastructure\Twig;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Phil\Recurrence\Recurrence;
use Phil\Recurrence\RecurrenceRule;
use Phil\Recurrence\SchemaOrg;
use Phil\RecurrenceBundle\Application\RuleDescription;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/**
 * Rules in templates: in words, in the request's language unless one is given, and their occurrences.
 */
final readonly class RecurrenceExtension
{
    public function __construct(
        private RuleDescription $description,
    ) {
    }

    /**
     * The rule in words; an end time is read as a day in the zone given — the first occurrence's, or its time zone
     * name — when there is one.
     */
    #[AsTwigFilter(name: 'rrule_text')]
    public function text(?RecurrenceRule $rule, ?string $locale = null, DateTimeInterface|DateTimeZone|string|null $timezone = null): string
    {
        if (!$rule instanceof RecurrenceRule) {
            return '';
        }

        return $this->description->describe($rule, $locale, match (true) {
            $timezone instanceof DateTimeInterface => $timezone->getTimezone(),
            \is_string($timezone) => new DateTimeZone($timezone),
            default => $timezone,
        });
    }

    /**
     * The occurrences from a first one, from a moment, at most so many.
     *
     * @return list<DateTimeImmutable>
     */
    #[AsTwigFunction(name: 'rrule_occurrences')]
    public function occurrences(DateTimeInterface $start, ?RecurrenceRule $rule, int $limit = 10, ?DateTimeInterface $from = null): array
    {
        return $rule instanceof RecurrenceRule ? Recurrence::of($start, $rule)->between($from, null, $limit) : [DateTimeImmutable::createFromInterface($start)];
    }

    /**
     * The recurrence as a schema.org `Schedule`, for the `eventSchedule` of an `Event`; `null` without a rule.
     *
     * @return array<string, mixed>|null
     */
    #[AsTwigFunction(name: 'rrule_schema')]
    public function schema(DateTimeInterface $start, ?RecurrenceRule $rule): ?array
    {
        return $rule instanceof RecurrenceRule ? SchemaOrg::of(Recurrence::of($start, $rule)) : null;
    }
}
