<?php

declare(strict_types=1);

namespace Phil\Typography\Fixer;

use Phil\Typography\Locale;

/**
 * How a dash between two words is written: spaced en dash — `this – that`, most of Europe and British English — or
 * closed em dash — `this—that`, American and Canadian English.
 */
enum DashStyle
{
    case Spaced;
    case Closed;

    public static function for(Locale $locale): self
    {
        return 'en' === $locale->language && \in_array($locale->region, [null, 'US', 'CA', 'PH'], true) ? self::Closed : self::Spaced;
    }
}
