<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Security;

/**
 * A coarse name of a device from its user agent — a browser family and a system family, such as "Firefox on Linux" —,
 * enough for a person to recognise their sessions and for the bundle to tell a new device, and too little to track
 * anyone. The version numbers and the rest of the string are never kept.
 */
final class DeviceName
{
    private const array BROWSERS = ['Edg/' => 'Edge', 'OPR/' => 'Opera', 'Firefox/' => 'Firefox', 'Chrome/' => 'Chrome', 'Safari/' => 'Safari'];

    private const array SYSTEMS = ['Android' => 'Android', 'iPhone' => 'iOS', 'iPad' => 'iPadOS', 'Mac OS X' => 'macOS', 'Windows' => 'Windows', 'CrOS' => 'ChromeOS', 'Linux' => 'Linux'];

    private function __construct()
    {
    }

    public static function of(?string $userAgent): string
    {
        $userAgent = substr($userAgent ?? '', 0, 512);
        $browser = self::first(self::BROWSERS, $userAgent) ?? 'Browser';
        $system = self::first(self::SYSTEMS, $userAgent);

        return null === $system ? $browser : $browser.' on '.$system;
    }

    /**
     * @param array<string, string> $names
     */
    private static function first(array $names, string $userAgent): ?string
    {
        foreach ($names as $needle => $name) {
            if (str_contains($userAgent, $needle)) {
                return $name;
            }
        }

        return null;
    }
}
