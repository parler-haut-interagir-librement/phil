<?php

declare(strict_types=1);

namespace Phil\SocialCards\Card;

use Phil\SocialCards\Exception\InvalidCardTemplate;
use Phil\SocialCards\Text;

/**
 * The fingerprint of a card as stores and addresses take it: 32 lower-case hexadecimal digits, nothing a path could
 * climb out of.
 */
final class Fingerprint
{
    public const string PATTERN = '[0-9a-f]{32}';

    public static function isValid(string $fingerprint): bool
    {
        return 1 === preg_match('/^'.self::PATTERN.'$/D', $fingerprint);
    }

    public static function check(string $fingerprint): string
    {
        if (!self::isValid($fingerprint)) {
            throw InvalidCardTemplate::because(\sprintf('"%s" is not the fingerprint of a card: 32 lower-case hexadecimal digits.', Text::excerpt($fingerprint)));
        }

        return $fingerprint;
    }
}
