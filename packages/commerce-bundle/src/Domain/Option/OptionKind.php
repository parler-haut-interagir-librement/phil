<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Option;

/**
 * What an option of a sellable asks of the buyer, and what its value holds.
 */
enum OptionKind: string
{
    /** A text, typed by the buyer, picked from a list, or drawn from it after payment. */
    case Text = 'text';

    /** One choice among several, each with its label and perhaps its image: a frame, a background, a model. */
    case Choice = 'choice';

    /** One ink or technique among several, printed as a layer of its own: a glowing ink, a metallic one. */
    case Ink = 'ink';

    /** A value nobody chooses, drawn once the order is paid, one draw per unit. */
    case Draw = 'draw';

    /**
     * An amount the buyer chooses, from the option's minimum to its maximum, added to the unit price: a price the buyer
     * sets, a donation, a tip.
     */
    case Amount = 'amount';
}
