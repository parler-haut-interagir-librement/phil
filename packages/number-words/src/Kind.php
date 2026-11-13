<?php

declare(strict_types=1);

namespace Phil\NumberWords;

/**
 * What a spelled number is, for the corrections of a language.
 */
enum Kind: string
{
    /**
     * A cardinal said on its own, when counting: « vingt et un », « eins », « veintiuno ».
     */
    case Counting = 'counting';

    /**
     * A cardinal that agrees with a noun after it: « vingt et une pages », « veintiún dólares », « ein Euro ».
     */
    case Cardinal = 'cardinal';

    /**
     * An ordinal: « vingt et unième », « twenty-first ».
     */
    case Ordinal = 'ordinal';
}
