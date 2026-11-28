<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Tests\Unit\Infrastructure;

use Phil\Shifts\Roster\Roster;
use Phil\ShiftsBundle\Infrastructure\Http\ShiftsGuard;
use Phil\ShiftsBundle\Infrastructure\Twig\Components\MyShiftsComponent;
use Phil\ShiftsBundle\Infrastructure\Twig\Components\ShiftCardComponent;
use Phil\ShiftsBundle\Tests\Fixtures\FixedPerson;
use Phil\ShiftsBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

#[CoversClass(ShiftCardComponent::class)]
#[CoversClass(MyShiftsComponent::class)]
final class ComponentsTest extends TestCase
{
    public function testTheCardOfAShift(): void
    {
        $stage = new Stage();
        $stage->rosters->plan(Stage::desk());
        $stage->rosters->signUp('desk-am', 'ada', []);

        $card = new ShiftCardComponent($stage->rosters, new FixedPerson('ada'), $this->guard(), 'America/Toronto');

        self::assertNull($card->getCurrent());
        self::assertNull($card->getStatus());
        self::assertFalse($card->isOpen());
        self::assertFalse($card->isConfirmed());
        $card->id = 'desk-am';
        self::assertSame('desk-am', $card->getCurrent()?->shift->id);
        self::assertSame('confirmed', $card->getStatus());
        self::assertTrue($card->isConfirmed());
        self::assertTrue($card->isOpen());
        self::assertSame('America/Toronto', $card->getZone());
        self::assertNotSame('', $card->getToken());
        self::assertTrue($card->actions);

        $given = new ShiftCardComponent($stage->rosters, new FixedPerson(null), $this->guard(), 'UTC');
        $given->roster = $stage->rosters->find('desk-am');
        self::assertSame('desk-am', $given->getCurrent()?->shift->id);
        self::assertNull($given->getStatus());

        $stranger = new ShiftCardComponent($stage->rosters, new FixedPerson('bob'), $this->guard(), 'UTC');
        $stranger->id = 'desk-am';
        self::assertNull($stranger->getStatus());
        self::assertFalse($stranger->isConfirmed());

        $stage->clock->modify('2026-11-28 09:00:00 UTC');
        self::assertFalse($card->isOpen());
    }

    public function testTheShiftsOfThePersonSignedIn(): void
    {
        $stage = new Stage();
        $stage->rosters->plan(Stage::desk());
        $stage->rosters->plan(Stage::desk('pm', '13:00:00', '14:00:00'));
        $stage->rosters->plan(Stage::desk('late', '15:00:00', '16:00:00'));
        foreach (['desk-am', 'pm', 'late'] as $shift) {
            $stage->rosters->signUp($shift, 'ada', []);
        }

        $mine = new MyShiftsComponent($stage->rosters, new FixedPerson('ada'), $stage->clock, 'America/Toronto');
        self::assertSame('ada', $mine->getPerson());
        self::assertSame('America/Toronto', $mine->getZone());
        self::assertSame(5, $mine->limit);
        self::assertSame(['desk-am', 'pm', 'late'], $this->ids($mine->getUpcoming()));
        $mine->limit = 2;
        self::assertSame(['desk-am', 'pm'], $this->ids($mine->getUpcoming()));
        $mine->limit = 0;
        self::assertSame(['desk-am'], $this->ids($mine->getUpcoming()));
        $mine->limit = 99;

        $stage->clock->modify('2026-11-28 14:00:00 UTC');
        self::assertSame(['late'], $this->ids($mine->getUpcoming()));
        self::assertSame(240, $mine->getServed()?->minutes);

        $nobody = new MyShiftsComponent($stage->rosters, new FixedPerson(null), $stage->clock, 'UTC');
        self::assertNull($nobody->getPerson());
        self::assertSame([], $nobody->getUpcoming());
        self::assertNull($nobody->getServed());
    }

    public function testAtMostFiftyShiftsAreShown(): void
    {
        $stage = new Stage();

        for ($index = 0; $index < 51; ++$index) {
            $from = \sprintf('%02d:%02d:00', intdiv($index * 10, 60), ($index * 10) % 60);
            $to = \sprintf('%02d:%02d:00', intdiv($index * 10 + 5, 60), ($index * 10 + 5) % 60);
            $stage->rosters->plan(Stage::desk('s'.$index, $from, $to));
            $stage->rosters->signUp('s'.$index, 'ada', []);
        }

        $mine = new MyShiftsComponent($stage->rosters, new FixedPerson('ada'), $stage->clock, 'UTC');
        $mine->limit = 100;

        self::assertCount(50, $mine->getUpcoming());
        $mine->limit = 50;
        self::assertCount(50, $mine->getUpcoming());
        $mine->limit = 49;
        self::assertCount(49, $mine->getUpcoming());
    }

    /**
     * @param list<Roster> $rosters
     *
     * @return list<string>
     */
    private function ids(array $rosters): array
    {
        return array_map(static fn (Roster $roster): string => $roster->shift->id, $rosters);
    }

    private function guard(): ShiftsGuard
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $stack = new RequestStack([$request]);

        return new ShiftsGuard(new CsrfTokenManager(null, new SessionTokenStorage($stack)));
    }
}
