<?php

declare(strict_types=1);

namespace Phil\SocialCards;

/**
 * The card X (formerly Twitter) and the readers of its tags draw for a link, `twitter:card`.
 */
enum TwitterCard: string
{
    /** A square thumbnail beside the title. */
    case Summary = 'summary';

    /** The image across the card, above the title: the card of a page with a 1200×630 image. */
    case SummaryLargeImage = 'summary_large_image';
}
