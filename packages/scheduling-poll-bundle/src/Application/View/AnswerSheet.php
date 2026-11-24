<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Application\View;

use DateTimeImmutable;
use DateTimeZone;

/**
 * What a respondent's page shows: the poll, the dates with their own answers in the time zone they read them in,
 * whether they may still change them, and the answers of all when they are theirs to see. The token of the link is not
 * in it: the page has it from the request.
 */
final readonly class AnswerSheet
{
    /**
     * @param list<SlotLine> $slots
     */
    public function __construct(
        public string $poll,
        public string $respondent,
        public string $title,
        public ?string $description,
        public ?string $location,
        public ?string $name,
        public DateTimeZone $timezone,
        public DateTimeImmutable $deadline,
        public array $slots,
        public bool $answered,
        public bool $editable,
        public bool $closed,
        public ?SlotLine $chosen,
        public ?ResultsView $results,
    ) {
    }
}
