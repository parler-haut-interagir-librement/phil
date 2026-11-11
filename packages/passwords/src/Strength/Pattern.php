<?php

declare(strict_types=1);

namespace Phil\Passwords\Strength;

/**
 * The kind of a part of a password, as the estimator recognised it.
 */
enum Pattern: string
{
    /** A word of a dictionary, maybe reversed or with substitutions such as `p@ssw0rd`. */
    case Dictionary = 'dictionary';

    /** Keys next to each other on a keyboard, such as `qwerty` or `zxcvb`. */
    case Spatial = 'spatial';

    /** Characters that follow each other at a steady step, such as `abcd`, `9876` or `aceg`. */
    case Sequence = 'sequence';

    /** A part written several times in a row, such as `aaaa` or `abcabc`. */
    case Repeat = 'repeat';

    /** A date of day, month and year, such as `1987-03-14` or `140387`. */
    case Date = 'date';

    /** A year from 1900 to 2099. */
    case Year = 'year';

    /** Characters that follow none of the other patterns, guessed one by one. */
    case Bruteforce = 'bruteforce';
}
