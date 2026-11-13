<?php

declare(strict_types=1);

namespace Phil\NumberWords;

/**
 * How French numbers are hyphenated.
 */
enum FrenchSpelling: string
{
    /**
     * Hyphens between tens and units below one hundred only, `et` between spaces: « deux cent vingt et un ».
     */
    case Traditional = 'traditional';

    /**
     * The 1990 spelling rectifications: a hyphen between every numeral, the nouns `million`, `milliard` and their kin
     * kept apart by spaces: « deux-cent-vingt-et-un », « trois millions deux-cent-mille ».
     */
    case Rectified1990 = 'rectified-1990';
}
