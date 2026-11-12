<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Device;

/**
 * The device of a page view, kept in three coarse classes and nothing finer.
 *
 * The class comes from a small rule over the `User-Agent` ({@see self::fromUserAgent()}), not from a device
 * detection library: a finer class (a model, a browser version) adds nothing an aggregate needs and much of what
 * a fingerprint is made of.
 */
enum DeviceClass: string
{
    case Desktop = 'desktop';
    case Tablet = 'tablet';
    case Mobile = 'mobile';

    /**
     * Tablet first, because tablet user agents also carry mobile tokens; then mobile; anything else, including an
     * empty user agent, is a desktop. An iPad that presents itself as a Mac is counted as a desktop: the user
     * agent is all the rule reads. A Windows desktop whose user agent carries `Tablet PC` (pen input support on
     * a Windows PC, not a tablet) is not matched by the tablet token.
     */
    public static function fromUserAgent(string $userAgent): self
    {
        if (1 === preg_match('/iPad|Tablet(?! PC)|PlayBook|Kindle|Silk\//i', $userAgent)
            || (1 === preg_match('/Android/i', $userAgent) && 1 !== preg_match('/Mobile/i', $userAgent))) {
            return self::Tablet;
        }

        if (1 === preg_match('/Mobi|iPhone|iPod|Android|Windows Phone|BlackBerry|BB10|Opera Mini/i', $userAgent)) {
            return self::Mobile;
        }

        return self::Desktop;
    }
}
