<?php

declare(strict_types=1);

namespace Phil\ProductFeeds;

/**
 * Who an item is made for, by age: readers ask it of clothing and accessories, with the gender and the size.
 */
enum AgeGroup: string
{
    case Newborn = 'newborn';
    case Infant = 'infant';
    case Toddler = 'toddler';
    case Kids = 'kids';
    case Adult = 'adult';
}
