<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application;

use Phil\NewsletterBundle\Application\Exception\LinkRefused;
use SensitiveParameter;

/**
 * The key of the newsletter, `secret` of the configuration, and the keys derived from it for each use — the VERP
 * tags, the suppression hashes —, derived when the container builds the service that needs them, from the value the
 * environment gives at that moment, never from the placeholder the configuration holds.
 *
 * A key holds at least 16 bytes, and one that begins with "change-me" or "changeme", in any case, is a placeholder
 * copied from an example, known to everyone: both are refused.
 */
final class NewsletterKey
{
    public const int MIN_LENGTH = 16;

    private const string PLACEHOLDER = '/^change-?me/i';

    /**
     * @throws LinkRefused when the key is shorter than 16 bytes or a placeholder
     */
    public static function check(#[SensitiveParameter] string $key): string
    {
        if (\strlen($key) < self::MIN_LENGTH) {
            throw LinkRefused::because(\sprintf('The key of the newsletter links holds at least %d bytes.', self::MIN_LENGTH));
        }

        if (1 === preg_match(self::PLACEHOLDER, $key)) {
            throw LinkRefused::because('The key of the newsletter links is a placeholder: give it random bytes of its own.');
        }

        return $key;
    }

    /**
     * The key of one use, an HMAC-SHA256 of the use's name under the newsletter's key, in hexadecimal.
     *
     * @throws LinkRefused when the key is shorter than 16 bytes or a placeholder
     */
    public static function derive(#[SensitiveParameter] string $key, string $use): string
    {
        return hash_hmac('sha256', $use, self::check($key));
    }
}
