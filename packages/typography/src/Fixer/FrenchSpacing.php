<?php

declare(strict_types=1);

namespace Phil\Typography\Fixer;

use Phil\Typography\Locale;

/**
 * The French rules for the space before high punctuation.
 *
 * In France, Belgium, Switzerland and most of the French-speaking world, a narrow no-break space goes before `;`, `!`
 * and `?`, and a no-break space before `:` and inside `« »`. In Canada, the Office québécois de la langue française
 * keeps the no-break space before `:` and inside `« »`, and puts no space before `;`, `!` and `?`.
 */
enum FrenchSpacing
{
    case France;
    case Canada;

    public static function for(Locale $locale): self
    {
        return 'CA' === $locale->region ? self::Canada : self::France;
    }
}
