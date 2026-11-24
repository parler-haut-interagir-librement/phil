<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Application\View;

use Phil\SchedulingPollBundle\Domain\Availability;

/**
 * One respondent's answers as a table shows them: their name — `null` once erased, a page then writes a number —, their
 * place in the order they joined, from 1, whether the row is the viewer's own, and their answer to each date, by slot
 * identifier.
 */
final readonly class ResponseRow
{
    /**
     * @param array<string, Availability> $answers
     */
    public function __construct(
        public string $respondent,
        public int $number,
        public ?string $name,
        public array $answers,
        public bool $own = false,
    ) {
    }

    public function answerTo(string $slot): ?Availability
    {
        return $this->answers[$slot] ?? null;
    }
}
