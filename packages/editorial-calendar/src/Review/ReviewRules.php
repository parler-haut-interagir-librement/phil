<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\Review;

use Phil\EditorialCalendar\Exception\InvalidItem;

/**
 * What the review of a calendar looks for besides repeated content and unready items: publications too close together,
 * and stretches of time without any.
 */
final readonly class ReviewRules
{
    /**
     * @param int      $minimumSpacing the seconds wanted at least between two publications, 0 to ignore their spacing
     * @param int|null $maximumGap     the seconds without publication beyond which a gap is reported, `null` to report none
     *
     * @throws InvalidItem when the spacing is negative or the gap below one second
     */
    public function __construct(
        public int $minimumSpacing = 0,
        public ?int $maximumGap = null,
    ) {
        if ($minimumSpacing < 0 || (null !== $maximumGap && $maximumGap < 1)) {
            throw InvalidItem::because('The spacing is 0 seconds or more, and a gap at least one second.');
        }
    }
}
