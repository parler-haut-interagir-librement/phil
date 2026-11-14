<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Infrastructure;

use Phil\SettingsBundle\Application\Visitor\PreferenceCookieFormat;
use Phil\SettingsBundle\Infrastructure\Http\PreferenceCookie;
use Phil\SettingsBundle\Tests\Unit\Fixture\Catalogs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(PreferenceCookie::class)]
final class PreferenceCookieTest extends TestCase
{
    public function testTheCookieOfTheMainRequestIsRead(): void
    {
        self::assertSame([], new PreferenceCookie(new RequestStack(), new PreferenceCookieFormat(Catalogs::sample()))->values(), 'no request');

        $requests = new RequestStack([
            new Request(cookies: ['phil_preferences' => 'display.motion=reduce']),
            new Request(cookies: ['phil_preferences' => 'display.theme=dark']),
        ]);
        self::assertSame(['display.motion' => 'reduce'], new PreferenceCookie($requests, new PreferenceCookieFormat(Catalogs::sample()))->values(), 'the main request');

        $other = new RequestStack([new Request(cookies: ['prefs' => 'display.theme=dark', 'phil_preferences' => 'display.motion=reduce'])]);
        self::assertSame(['display.theme' => 'dark'], new PreferenceCookie($other, new PreferenceCookieFormat(Catalogs::sample()), 'prefs')->values());

        $none = new RequestStack([new Request()]);
        self::assertSame([], new PreferenceCookie($none, new PreferenceCookieFormat(Catalogs::sample()))->values(), 'no cookie');
    }

    public function testTheCookieIsStrictlyNecessaryAndKeptFromScriptsAndOtherSites(): void
    {
        $cookie = new PreferenceCookie(new RequestStack(), new PreferenceCookieFormat(Catalogs::sample()), 'prefs', 3600)
            ->cookie(['display.motion' => 'reduce', 'author.public' => true], Request::create('https://example.com/'), 1_000_000);

        self::assertSame(['prefs', 'author.public=1&display.motion=reduce', 1_003_600, '/', null, true, true, true, Cookie::SAMESITE_LAX], [
            $cookie->getName(), $cookie->getValue(), $cookie->getExpiresTime(), $cookie->getPath(), $cookie->getDomain(), $cookie->isSecure(), $cookie->isHttpOnly(), $cookie->isRaw(), $cookie->getSameSite(),
        ]);
        self::assertStringStartsWith('prefs=author.public=1&display.motion=reduce;', (string) $cookie);

        $plain = new PreferenceCookie(new RequestStack(), new PreferenceCookieFormat(Catalogs::sample()))->cookie(['display.motion' => 'reduce'], Request::create('http://example.com/'), 0);
        self::assertSame(['phil_preferences', 31_536_000, false], [$plain->getName(), $plain->getExpiresTime(), $plain->isSecure()]);
    }

    public function testEveryDefaultClearsTheCookie(): void
    {
        $cookie = new PreferenceCookie(new RequestStack(), new PreferenceCookieFormat(Catalogs::sample()))->cookie(['display.motion' => 'system'], Request::create('/'), 1_000_000);

        self::assertNull($cookie->getValue());
        self::assertTrue($cookie->isCleared());
    }
}
