<?php

declare(strict_types=1);

namespace Phil\SafeHttp\Exception;

/**
 * An address as a message shows it: without its user information, query and fragment, which often hold a secret —
 * a password, a token, a signature —, `…@` standing for the user information and `?…` for a query or a fragment left
 * out, without control characters, and cut to 100 whole characters.
 *
 * @internal
 */
final class ShownAddress
{
    public const int MAX_LENGTH = 100;

    private function __construct()
    {
    }

    public static function of(string $address): string
    {
        $shown = (string) preg_replace(['/[\x00-\x1F\x7F]++/', '/[?#].*+$/s', '~^([a-z][a-z0-9+.\-]*+://)[^/@]*+@~i'], ['', '?…', '$1…@'], $address);

        return mb_substr($shown, 0, self::MAX_LENGTH);
    }

    /**
     * A text, such as the message of a transport error, with each address in it shown so, on one line.
     */
    public static function in(string $text): string
    {
        return (string) preg_replace_callback(
            '~\b[a-z][a-z0-9+.\-]*+://[^\s"\'<>]++~i',
            static fn (array $match): string => self::of($match[0]),
            (string) preg_replace('/[\x00-\x1F\x7F]++/', ' ', $text),
        );
    }
}
