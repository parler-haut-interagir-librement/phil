<?php

declare(strict_types=1);

namespace Phil\SafeHttp;

use Phil\SafeHttp\Exception\InvalidLimits;

/**
 * What one read of another site may cost: the silence the host may keep, the whole duration of the read and of its
 * redirects, the bytes of the body kept, the redirects followed, and the `User-Agent` the requests carry — a name for
 * the site and a way to reach whoever runs it, as the robots of link previews give.
 */
final readonly class Limits
{
    public const float MAX_TIMEOUT = 60.0;

    public const float MAX_DURATION = 120.0;

    public const int MIN_BYTES = 1024;

    public const int MAX_BYTES = 64 * 1024 * 1024;

    public const int MAX_REDIRECTS = 10;

    public function __construct(
        public float $timeout = 5.0,
        public float $maxDuration = 10.0,
        public int $maxBytes = 1_048_576,
        public int $maxRedirects = 3,
        public string $userAgent = 'PhilSafeHttp/0.1',
    ) {
        if ($timeout < 0.1 || $timeout > self::MAX_TIMEOUT) {
            throw InvalidLimits::outOfRange('The timeout', $timeout, 0.1, self::MAX_TIMEOUT);
        }

        if ($maxDuration < 0.1 || $maxDuration > self::MAX_DURATION) {
            throw InvalidLimits::outOfRange('The whole duration', $maxDuration, 0.1, self::MAX_DURATION);
        }

        if ($maxBytes < self::MIN_BYTES || $maxBytes > self::MAX_BYTES) {
            throw InvalidLimits::outOfRange('The bytes read', $maxBytes, self::MIN_BYTES, self::MAX_BYTES);
        }

        if ($maxRedirects < 0 || $maxRedirects > self::MAX_REDIRECTS) {
            throw InvalidLimits::outOfRange('The redirects', $maxRedirects, 0, self::MAX_REDIRECTS);
        }

        if (!self::isHeaderValue($userAgent)) {
            throw InvalidLimits::header('User-Agent');
        }
    }

    /**
     * A value a header can carry as it is: not empty, on one line, without a control character.
     */
    public static function isHeaderValue(string $value): bool
    {
        return '' !== trim($value) && 1 !== preg_match('/[\x00-\x1F\x7F]/', $value);
    }
}
