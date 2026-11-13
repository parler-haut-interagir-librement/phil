<?php

declare(strict_types=1);

namespace Phil\Citations\Style;

/**
 * How a style orders its bibliography.
 */
enum BibliographyOrder: string
{
    /**
     * By author, then year, then title, in the collation of the language.
     */
    case Alphabetical = 'alphabetical';

    /**
     * In the order the references are first cited, the references never cited after them.
     */
    case Citation = 'citation';
}
