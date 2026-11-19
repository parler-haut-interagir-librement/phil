<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Domain\Print;

/**
 * What an element of a print layout draws in its zone.
 */
enum PrintElementKind: string
{
    /** A fixed image, the same on every product it applies to: an object of the base or convention version. */
    case Image = 'image';
    /** The text of a text option, typed, picked or drawn, in the option's imposed font and zone. */
    case Text = 'text';
    /** The image of the choice made on a choice or ink option: a tombstone among several. */
    case Choice = 'choice';
    /** One image per die of a draw option, each die's result picking the image of its slot: the objects of a loot box. */
    case Dice = 'dice';
}
