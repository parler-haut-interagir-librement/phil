<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\WaitlistBundle\Domain\Exception\InvalidWaitlist;
use Phil\WaitlistBundle\Domain\Model\Waitlist;
use Phil\WaitlistBundle\Domain\Status\WaitlistMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Waitlist::class)]
#[CoversClass(InvalidWaitlist::class)]
final class WaitlistTest extends TestCase
{
    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-01 12:00:00 UTC');
    }

    private function waitlist(WaitlistMode $mode = WaitlistMode::Queue, int $window = 3_600): Waitlist
    {
        return Waitlist::open('session', 'S-1', $mode, $window, $this->now());
    }

    public function testAListIsOpenedForASubject(): void
    {
        $waitlist = $this->waitlist();

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/', $waitlist->id);
        self::assertSame(['session', 'S-1', WaitlistMode::Queue, 3_600, 0], [$waitlist->subjectType(), $waitlist->subjectId(), $waitlist->mode(), $waitlist->offerWindow(), $waitlist->heldPlaces()]);
        self::assertEquals($this->now(), $waitlist->createdAt());
        self::assertFalse($waitlist->isClosed());
        self::assertNull($waitlist->closedAt());
        self::assertNull($waitlist->drawSeed());
        self::assertNull($waitlist->drawnAt());
        self::assertTrue($waitlist->isOffering());
    }

    /**
     * @return iterable<string, array{string, string, int, string}>
     */
    public static function invalidOpenings(): iterable
    {
        yield 'kind with a capital' => ['Session', 'S-1', 3_600, 'A subject kind'];
        yield 'kind starting with a digit' => ['1session', 'S-1', 3_600, 'A subject kind'];
        yield 'kind too long' => [str_repeat('a', 65), 'S-1', 3_600, 'A subject kind'];
        yield 'kind with a line feed' => ["session\n", 'S-1', 3_600, 'A subject kind'];
        yield 'identifier with an at sign' => ['session', 'someone@example.com', 3_600, 'A subject identifier'];
        yield 'empty identifier' => ['session', '', 3_600, 'A subject identifier'];
        yield 'window too short' => ['session', 'S-1', 59, 'An offer is held from 60 to 2592000 seconds.'];
        yield 'window too long' => ['session', 'S-1', 2_592_001, 'An offer is held from 60 to 2592000 seconds.'];
    }

    #[DataProvider('invalidOpenings')]
    public function testAnOpeningOutOfBoundsIsRefused(string $type, string $id, int $window, string $message): void
    {
        $this->expectException(InvalidWaitlist::class);
        $this->expectExceptionMessageIsOrContains($message);

        Waitlist::open($type, $id, WaitlistMode::Queue, $window, $this->now());
    }

    public function testTheBoundsAreAccepted(): void
    {
        $short = Waitlist::open(str_repeat('a', 64), str_repeat('9', 190), WaitlistMode::Queue, 60, $this->now());
        $long = Waitlist::open('a', 'x', WaitlistMode::Queue, 2_592_000, $this->now());

        self::assertSame([60, 2_592_000], [$short->offerWindow(), $long->offerWindow()]);
    }

    public function testTheDeadlineIsTheWindowAfterNow(): void
    {
        self::assertEquals(new DateTimeImmutable('2026-11-01 13:00:00 UTC'), $this->waitlist()->offerDeadline($this->now()));
    }

    public function testTheWindowChanges(): void
    {
        $waitlist = $this->waitlist();
        $waitlist->changeOfferWindow(60);
        self::assertSame(60, $waitlist->offerWindow());

        $this->expectException(InvalidWaitlist::class);
        $waitlist->changeOfferWindow(59);
    }

    public function testPlacesAreHeldAndTaken(): void
    {
        $waitlist = $this->waitlist();
        $waitlist->hold(3);
        $waitlist->hold(1);
        $waitlist->take(4);
        self::assertSame(0, $waitlist->heldPlaces());

        $waitlist->hold(Waitlist::MAX_HELD);
        self::assertSame(Waitlist::MAX_HELD, $waitlist->heldPlaces());
    }

    public function testNoFewerThanOnePlaceIsHeld(): void
    {
        $this->expectException(InvalidWaitlist::class);
        $this->expectExceptionMessageIsOrContains('At least one place is freed.');

        $this->waitlist()->hold(0);
    }

    public function testAListHoldsABoundedNumberOfPlaces(): void
    {
        $waitlist = $this->waitlist();
        $waitlist->hold(Waitlist::MAX_HELD - 1);

        $this->expectException(InvalidWaitlist::class);
        $this->expectExceptionMessageIsOrContains('A waiting list holds 1000000 places at most.');

        $waitlist->hold(2);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidTakes(): iterable
    {
        yield 'none' => [0];
        yield 'more than held' => [3];
    }

    #[DataProvider('invalidTakes')]
    public function testOnlyThePlacesHeldAreTaken(int $places): void
    {
        $waitlist = $this->waitlist();
        $waitlist->hold(2);

        $this->expectException(InvalidWaitlist::class);
        $this->expectExceptionMessageIsOrContains('From 1 to the 2 places held are taken.');

        $waitlist->take($places);
    }

    public function testAListClosesAndReopens(): void
    {
        $waitlist = $this->waitlist();
        $waitlist->close($this->now());
        self::assertTrue($waitlist->isClosed());
        self::assertEquals($this->now(), $waitlist->closedAt());

        $waitlist->reopen();
        self::assertFalse($waitlist->isClosed());
        self::assertNull($waitlist->closedAt());
    }

    public function testAClosedListIsNotClosedAgain(): void
    {
        $waitlist = $this->waitlist();
        $waitlist->close($this->now());

        $this->expectException(InvalidWaitlist::class);
        $this->expectExceptionMessageIsOrContains('A closed waiting list is not closed again.');

        $waitlist->close($this->now());
    }

    public function testAnOpenListIsNotReopened(): void
    {
        $this->expectException(InvalidWaitlist::class);
        $this->expectExceptionMessageIsOrContains('Only a closed waiting list is reopened.');

        $this->waitlist()->reopen();
    }

    public function testADrawListOffersOnceDrawn(): void
    {
        $waitlist = $this->waitlist(WaitlistMode::Draw);
        self::assertFalse($waitlist->isOffering());

        $waitlist->draw('seed-2026', $this->now());

        self::assertTrue($waitlist->isOffering());
        self::assertSame('seed-2026', $waitlist->drawSeed());
        self::assertEquals($this->now(), $waitlist->drawnAt());
    }

    public function testAQueueIsNotDrawn(): void
    {
        $this->expectException(InvalidWaitlist::class);
        $this->expectExceptionMessageIsOrContains('Only a waiting list in draw mode is drawn.');

        $this->waitlist()->draw('seed-2026', $this->now());
    }

    public function testAListIsDrawnOnce(): void
    {
        $waitlist = $this->waitlist(WaitlistMode::Draw);
        $waitlist->draw('seed-2026', $this->now());

        $this->expectException(InvalidWaitlist::class);
        $this->expectExceptionMessageIsOrContains('A waiting list is drawn once.');

        $waitlist->draw('seed-2027', $this->now());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidSeeds(): iterable
    {
        yield 'too short' => ['1234567'];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'a space' => ['seed 2026'];
        yield 'a line feed at the end' => ["seed-2026\n"];
    }

    #[DataProvider('invalidSeeds')]
    public function testASeedIsChecked(string $seed): void
    {
        $this->expectException(InvalidWaitlist::class);
        $this->expectExceptionMessageIsOrContains('A seed is 8 to 64 letters');

        $this->waitlist(WaitlistMode::Draw)->draw($seed, $this->now());
    }

    public function testTheSeedBoundsAreAccepted(): void
    {
        $short = $this->waitlist(WaitlistMode::Draw);
        $short->draw('12345678', $this->now());

        $long = $this->waitlist(WaitlistMode::Draw);
        $long->draw(str_repeat('Az_.:-', 10).'abcd', $this->now());

        self::assertSame(['12345678', str_repeat('Az_.:-', 10).'abcd'], [$short->drawSeed(), $long->drawSeed()]);
    }

    public function testASinglePlaceHeldIsTaken(): void
    {
        $waitlist = Waitlist::open('session', 'S-1', WaitlistMode::Queue, 3_600, new DateTimeImmutable('2026-11-01 08:00 UTC'));
        $waitlist->hold(1);

        $waitlist->take(1);

        self::assertSame(0, $waitlist->heldPlaces());
    }
}
