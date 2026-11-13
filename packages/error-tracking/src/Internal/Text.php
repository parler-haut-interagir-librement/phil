<?php

declare(strict_types=1);

namespace Phil\ErrorTracking\Internal;

/**
 * The bounds every text of an event is held to: valid UTF-8 cut to a number of characters, and the names PHP gives
 * closures and anonymous classes reduced to a form that holds no path, no line and no NUL byte.
 *
 * @internal
 */
final class Text
{
    private function __construct()
    {
    }

    public static function clip(string $text, int $length): string
    {
        return mb_substr(mb_scrub($text, 'UTF-8'), 0, $length, 'UTF-8');
    }

    /**
     * A class or function name: `{closure:App\Job::run():12}` becomes `{closure}`, and the file, line and counter PHP
     * appends to an anonymous class after a NUL byte are dropped.
     */
    public static function symbol(string $name, int $length): string
    {
        return self::clip((string) preg_replace(['/@anonymous\x00.*?(?=::|$)/s', '/\{closure:[^}]*\}/'], ['@anonymous', '{closure}'], $name), $length);
    }
}
