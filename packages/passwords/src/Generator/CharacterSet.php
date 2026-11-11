<?php

declare(strict_types=1);

namespace Phil\Passwords\Generator;

/**
 * The kinds of characters a generated password draws from. The symbols leave out the space, quotes and the backslash,
 * which other systems often mishandle.
 */
enum CharacterSet: string
{
    case Lowercase = 'lowercase';
    case Uppercase = 'uppercase';
    case Digits = 'digits';
    case Symbols = 'symbols';

    /** The characters read one for another in many typefaces. */
    public const string AMBIGUOUS = 'Il1|O0o';

    public function characters(): string
    {
        return match ($this) {
            self::Lowercase => 'abcdefghijklmnopqrstuvwxyz',
            self::Uppercase => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            self::Digits => '0123456789',
            self::Symbols => '!#$%&()*+,-./:;<=>?@[]^_{|}~',
        };
    }
}
