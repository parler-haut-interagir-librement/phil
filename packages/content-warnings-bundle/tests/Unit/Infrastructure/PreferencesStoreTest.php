<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle\Tests\Unit\Infrastructure;

use Phil\ContentWarnings\Display;
use Phil\ContentWarnings\WarningPreferences;
use Phil\ContentWarningsBundle\Application\Exception\PreferencesNotSaved;
use Phil\ContentWarningsBundle\Infrastructure\Memory\InMemoryWarningPreferences;
use Phil\ContentWarningsBundle\Infrastructure\Session\SessionWarningPreferences;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

#[CoversClass(InMemoryWarningPreferences::class)]
#[CoversClass(SessionWarningPreferences::class)]
final class PreferencesStoreTest extends TestCase
{
    public function testPreferencesInMemoryAreResetToTheDefaults(): void
    {
        $store = new InMemoryWarningPreferences(Display::Show);
        self::assertEquals(WarningPreferences::defaults(Display::Show), $store->current());

        $store->save(WarningPreferences::defaults()->withTheme('gore', Display::Hide));
        self::assertSame(['gore'], $store->current()->themesWith(Display::Hide));

        $store->reset();
        self::assertEquals(WarningPreferences::defaults(Display::Show), $store->current());
        self::assertEquals(WarningPreferences::defaults(), new InMemoryWarningPreferences()->current());
    }

    public function testPreferencesAreKeptInTheSession(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);

        $requests = new RequestStack([$request]);
        $store = new SessionWarningPreferences($requests, Display::Show);

        $store->save(WarningPreferences::defaults()->withTheme('gore', Display::Hide));
        self::assertSame(['default' => 'fold', 'themes' => ['gore' => ['display' => 'hide']], 'categories' => []], $session->get(SessionWarningPreferences::SESSION_KEY));

        $session->save();
        $next = new Request(cookies: [$session->getName() => $session->getId()]);
        $next->setSession($session);
        self::assertSame(['gore'], new SessionWarningPreferences(new RequestStack([$next]))->current()->themesWith(Display::Hide));
    }

    public function testTheMainRequestHoldsTheSession(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set(SessionWarningPreferences::SESSION_KEY, ['default' => 'hide']);
        $session->save();

        $main = new Request(cookies: [$session->getName() => $session->getId()]);
        $main->setSession($session);

        $store = new SessionWarningPreferences(new RequestStack([$main, new Request()]));

        self::assertSame(Display::Hide, $store->current()->default);
        $store->save(WarningPreferences::defaults(Display::Show));
        self::assertSame(['default' => 'show', 'themes' => [], 'categories' => []], $session->get(SessionWarningPreferences::SESSION_KEY));
    }

    public function testWithoutAPreviousSessionOrReadablePreferencesTheDefaultsAreGiven(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set(SessionWarningPreferences::SESSION_KEY, ['default' => 'hide']);

        $fresh = new Request();
        $fresh->setSession($session);

        self::assertEquals(WarningPreferences::defaults(Display::Show), new SessionWarningPreferences(new RequestStack([$fresh]), Display::Show)->current());
        self::assertEquals(WarningPreferences::defaults(), new SessionWarningPreferences(new RequestStack())->current());

        foreach ([['default' => 'blur'], 'hide'] as $stored) {
            $session->set(SessionWarningPreferences::SESSION_KEY, $stored);
            $session->save();
            $request = new Request(cookies: [$session->getName() => $session->getId()]);
            $request->setSession($session);
            self::assertEquals(WarningPreferences::defaults(Display::Show), new SessionWarningPreferences(new RequestStack([$request]), Display::Show)->current());
        }
    }

    public function testWithoutASessionNothingIsSaved(): void
    {
        $refusals = 0;

        foreach ([new RequestStack(), new RequestStack([new Request()])] as $requests) {
            try {
                new SessionWarningPreferences($requests)->save(WarningPreferences::defaults());
            } catch (PreferencesNotSaved $exception) {
                self::assertSame('The warning preferences cannot be saved: there is no session.', $exception->getMessage());
                ++$refusals;
            }
        }

        self::assertSame(2, $refusals);
    }
}
