<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Support;

use Phil\SitesConsoleBundle\Domain\Signature\SigningKey;

/**
 * Three fixed key pairs, from fixed seeds: the console's, the next key of the console, and a site's.
 */
final class Keys
{
    public const string CONSOLE_SEED = 'AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8';

    public const string NEXT_SEED = 'QEFCQ0RFRkdISUpLTE1OT1BRUlNUVVZXWFlaW1xdXl8';

    public const string SITE_SEED = 'ICEiIyQlJicoKSorLC0uLzAxMjM0NTY3ODk6Ozw9Pj8';

    public static function console(): SigningKey
    {
        return SigningKey::fromBase64(self::CONSOLE_SEED);
    }

    public static function next(): SigningKey
    {
        return SigningKey::fromBase64(self::NEXT_SEED);
    }

    public static function site(): SigningKey
    {
        return SigningKey::fromBase64(self::SITE_SEED);
    }
}
