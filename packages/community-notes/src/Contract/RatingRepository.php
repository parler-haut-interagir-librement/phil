<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Contract;

use Phil\CommunityNotes\Rating;
use Phil\Resource\ResourceReference;

/**
 * Where ratings are kept, one per rater and note; it gives the history of two raters too.
 */
interface RatingRepository extends RatingHistory
{
    /**
     * Keeps the rating, replacing the rater's earlier one on the same note.
     */
    public function save(Rating $rating): void;

    /**
     * The ratings of a note, in no particular order.
     *
     * @return list<Rating>
     */
    public function ofNote(string $note): array;

    public function of(string $note, ResourceReference $rater): ?Rating;

    /**
     * The ratings a member gave, in no particular order: their part of an access or erasure request.
     *
     * @return list<Rating>
     */
    public function byRater(ResourceReference $rater): array;

    /**
     * Forgets the ratings of a member.
     */
    public function removeRater(ResourceReference $rater): void;

    /**
     * Forgets the ratings of a note.
     */
    public function removeNote(string $note): void;
}
