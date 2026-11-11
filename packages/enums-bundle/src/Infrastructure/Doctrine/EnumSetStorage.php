<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Doctrine;

/**
 * How an `EnumSet` column is stored.
 */
enum EnumSetStorage: string
{
    /** One integer: the values of the cases, or'ed. Needs an enum of bit flags. */
    case Bitmask = 'bitmask';

    /** A JSON list of the values of the cases, the names of a pure enum. Any enum. */
    case Json = 'json';
}
