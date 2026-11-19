<?php

declare(strict_types=1);

namespace Phil\Avatar;

use Phil\Avatar\Exception\InvalidAvatar;

/**
 * What an avatar is drawn from: a stable opaque identifier of the application, such as a user's UUID or a random value
 * kept on their profile. The same seed always draws the same avatar.
 *
 * Never an email address, nor a hash of one, which a dictionary of addresses reverses: the seed refuses anything with
 * an `@`, and holds visible ASCII only.
 */
final readonly class Seed
{
    public const int MAX_LENGTH = 255;

    /**
     * @throws InvalidAvatar
     */
    public function __construct(
        public string $value,
    ) {
        if ('' === $value || \strlen($value) > self::MAX_LENGTH || 1 !== preg_match('/^[\x21-\x7E]+$/D', $value)) {
            throw InvalidAvatar::because(\sprintf('A seed is 1 to %d visible ASCII characters.', self::MAX_LENGTH));
        }

        if (str_contains($value, '@')) {
            throw InvalidAvatar::because('A seed is an opaque identifier, never an email address.');
        }
    }
}
