<?php

declare(strict_types=1);

namespace Phil\Passwords\Generator;

/**
 * How the words of a passphrase are written.
 */
enum Capitalization: string
{
    /** All in lowercase. */
    case None = 'none';

    /** The first word capitalised, as a sentence. */
    case First = 'first';

    /** Every word capitalised. */
    case Words = 'words';

    /** Each word capitalised or not at random, which adds one bit of entropy for each word. */
    case Random = 'random';
}
