<?php

declare(strict_types=1);

namespace Phil\Sitemap;

/**
 * How often a page is likely to change, as the sitemap protocol spells it.
 */
enum ChangeFrequency: string
{
    case Always = 'always';
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Never = 'never';
}
