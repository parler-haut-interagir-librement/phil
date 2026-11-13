<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Memory;

use Override;
use Phil\CommunityNotes\Contract\RatingRepository;
use Phil\CommunityNotes\PairHistory;
use Phil\CommunityNotes\Rating;
use Phil\Resource\ResourceReference;

/**
 * The ratings in memory, for tests and demonstrations; the history of two raters is read from them. A history goes
 * over every rating kept: a store in a database reads it in one query.
 */
final class InMemoryRatingRepository implements RatingRepository
{
    /** @var array<string, array<string, Rating>> the ratings by note, then by rater */
    private array $ratings = [];

    #[Override]
    public function save(Rating $rating): void
    {
        $this->ratings[$rating->note][$rating->rater->toString()] = $rating;
    }

    #[Override]
    public function ofNote(string $note): array
    {
        return array_values($this->ratings[$note] ?? []);
    }

    #[Override]
    public function of(string $note, ResourceReference $rater): ?Rating
    {
        return $this->ratings[$note][$rater->toString()] ?? null;
    }

    #[Override]
    public function byRater(ResourceReference $rater): array
    {
        $ratings = [];

        foreach ($this->ratings as $byRater) {
            if (isset($byRater[$rater->toString()])) {
                $ratings[] = $byRater[$rater->toString()];
            }
        }

        return $ratings;
    }

    #[Override]
    public function removeRater(ResourceReference $rater): void
    {
        foreach (array_keys($this->ratings) as $note) {
            unset($this->ratings[$note][$rater->toString()]);
        }
    }

    #[Override]
    public function removeNote(string $note): void
    {
        unset($this->ratings[$note]);
    }

    #[Override]
    public function between(ResourceReference $first, ResourceReference $second, string $exceptNote): PairHistory
    {
        $shared = 0;
        $agreed = 0;

        foreach ($this->ratings as $note => $byRater) {
            $one = $byRater[$first->toString()] ?? null;
            $other = $byRater[$second->toString()] ?? null;

            if ($note === $exceptNote || !$one instanceof Rating || !$other instanceof Rating) {
                continue;
            }

            ++$shared;

            if ($one->verdict === $other->verdict) {
                ++$agreed;
            }
        }

        return PairHistory::of($shared, $agreed);
    }
}
