<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Catalogue;

/**
 * Which courses of a catalogue a filter on the price keeps: every one, the free ones, or the ones with a price.
 */
enum PriceFilter: string
{
    case Any = 'any';
    case Free = 'free';
    case Paid = 'paid';
}
