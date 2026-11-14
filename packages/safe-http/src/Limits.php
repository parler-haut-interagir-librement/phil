<?php

declare(strict_types=1);

namespace Phil\SafeHttp;

use Phil\SafeHttp\Exception\InvalidLimits;

/**
 * What one read of another site may cost: the silence the host may keep, the whole duration of the read and of its
 * redirects, the bytes of the body kept, the redirects followed, the `User-Agent` the requests carry — a name for the
 * site and a way to reach whoever runs it, as the robots of link previews give —, and the bytes and the count of the
 * headers of an answer.
 */
final readonly class Limits
{
    public const float MAX_TIMEOUT = 60.0;

    public const float MAX_DURATION = 120.0;

    public const int MIN_BYTES = 1024;

    public const int MAX_BYTES = 64 * 1024 * 1024;

    public const int MAX_REDIRECTS = 10;

    public const int MIN_HEADER_BYTES = 1024;

    public const int MAX_HEADER_BYTES = 1024 * 1024;

    public const int MAX_HEADERS = 1000;

    public function __construct(
        public float $timeout = 5.0,
        public float $maxDuration = 10.0,
        public int $maxBytes = 1_048_576,
        public int $maxRedirects = 3,
        public string $userAgent = 'PhilSafeHttp/0.1',
        public int $maxHeaderBytes = 65_536,
        public int $maxHeaders = 100,
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

        if ($maxHeaderBytes < self::MIN_HEADER_BYTES || $maxHeaderBytes > self::MAX_HEADER_BYTES) {
            throw InvalidLimits::outOfRange('The bytes of the headers', $maxHeaderBytes, self::MIN_HEADER_BYTES, self::MAX_HEADER_BYTES);
        }

        if ($maxHeaders < 1 || $maxHeaders > self::MAX_HEADERS) {
            throw InvalidLimits::outOfRange('The headers', $maxHeaders, 1, self::MAX_HEADERS);
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
