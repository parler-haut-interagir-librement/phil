<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Infrastructure\Http;

use Override;
use Phil\SettingsBundle\Application\Contract\VisitorPreferences;
use Phil\SettingsBundle\Application\Visitor\PreferenceCookieFormat;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A visitor's preferences in a cookie of their own browser, read from the main request. The cookie holds only what the
 * visitor asked to be remembered — how the site is displayed for them —, so it is strictly necessary and needs no
 * consent; it is sent over HTTPS only when the request is, kept from scripts and from other sites' requests.
 */
final readonly class PreferenceCookie implements VisitorPreferences
{
    public const string NAME = 'phil_preferences';

    /** A year, in seconds. */
    public const int LIFETIME = 31_536_000;

    public function __construct(
        private RequestStack $requests,
        private PreferenceCookieFormat $format,
        private string $name = self::NAME,
        private int $lifetime = self::LIFETIME,
    ) {
    }

    #[Override]
    public function values(): array
    {
        $cookie = $this->requests->getMainRequest()?->cookies->get($this->name);

        return \is_string($cookie) ? $this->format->parse($cookie) : [];
    }

    /**
     * The cookie that keeps the values given, or that clears it when every value is the default.
     *
     * @param array<string, mixed> $values by key
     */
    public function cookie(array $values, Request $request, int $now): Cookie
    {
        $written = $this->format->format($values);

        return Cookie::create($this->name)
            ->withValue('' === $written ? null : $written)
            ->withExpires($now + $this->lifetime)
            ->withPath('/')
            ->withSecure($request->isSecure())
            ->withHttpOnly(true)
            ->withRaw(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }
}
