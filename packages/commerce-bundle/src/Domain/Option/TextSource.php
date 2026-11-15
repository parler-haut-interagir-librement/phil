<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Option;

/**
 * Where the value of a text option comes from; a text option allows one source or several.
 */
enum TextSource: string
{
    /** Typed by the buyer, within the option's length and characters. */
    case Free = 'free';

    /** Picked by the buyer among the option's entries. */
    case List = 'list';

    /** Drawn among the option's entries once the order is paid, one draw per unit. */
    case Drawn = 'drawn';
}
