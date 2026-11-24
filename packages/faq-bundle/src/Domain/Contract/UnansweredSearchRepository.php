<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\FaqBundle\Domain\Model\UnansweredSearch;

/**
 * The totals of the searches that found no question.
 */
interface UnansweredSearchRepository
{
    /**
     * Adds one to the total of a normalized search in a language, and keeps the day it happened.
     */
    public function record(string $locale, string $query, DateTimeImmutable $day): void;

    /**
     * The searches most often without answer, the most frequent first.
     *
     * @param positive-int $limit
     *
     * @return list<UnansweredSearch>
     */
    public function mostFrequent(int $limit, ?string $locale = null): array;

    /**
     * Forgets one search, once a question answers it.
     */
    public function forget(string $locale, string $query): void;
}
