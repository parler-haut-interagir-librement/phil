<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;
use Phil\NewsletterBundle\Domain\ValueObject\SendWindow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SendWindow::class)]
final class SendWindowTest extends TestCase
{
    public function testItOpensOnItsDaysAndHoursInItsTimeZone(): void
    {
        $window = SendWindow::of([5, 1, 2, 3, 4, 1], '09:00', '17:30', 'America/Toronto');

        self::assertSame([1, 2, 3, 4, 5], $window->days);
        self::assertSame(540, $window->from);
        self::assertSame(1050, $window->until);
        self::assertTrue($window->contains(new DateTimeImmutable('2026-11-13 09:00:00 America/Toronto')));
        self::assertTrue($window->contains(new DateTimeImmutable('2026-11-13 17:29:59 America/Toronto')));
        self::assertFalse($window->contains(new DateTimeImmutable('2026-11-13 17:30:00 America/Toronto')));
        self::assertFalse($window->contains(new DateTimeImmutable('2026-11-13 08:59:59 America/Toronto')));
        self::assertFalse($window->contains(new DateTimeImmutable('2026-11-14 12:00:00 America/Toronto')));
        self::assertTrue($window->contains(new DateTimeImmutable('2026-11-13 14:00:00 UTC')));

        $in = new DateTimeImmutable('2026-11-13 10:00:00 America/Toronto');
        self::assertSame($in, $window->next($in));
        self::assertSame('2026-11-16 09:00 -05:00', $window->next(new DateTimeImmutable('2026-11-13 18:00:00 America/Toronto'))->format('Y-m-d H:i P'));
        self::assertSame('2026-11-13 09:00', $window->next(new DateTimeImmutable('2026-11-13 07:00:00 America/Toronto'))->format('Y-m-d H:i'));
        self::assertSame('2026-11-16 14:00:00 +00:00', $window->next(new DateTimeImmutable('2026-11-14 12:00:00 UTC'))->format('Y-m-d H:i:s P'));
    }

    public function testAlwaysIsEveryMinute(): void
    {
        $always = SendWindow::always();
        $at = new DateTimeImmutable('2026-11-15 23:59:59 UTC');

        self::assertTrue($always->contains($at));
        self::assertTrue($always->contains(new DateTimeImmutable('2026-11-15 00:00:00 UTC')));
        self::assertSame($at, $always->next($at));
        self::assertTrue(SendWindow::of([7], '00:00', '24:00')->contains($at));
    }

    /**
     * @param list<int> $days
     */
    #[DataProvider('refused')]
    public function testItRefuses(array $days, string $from, string $until, string $timezone, string $message): void
    {
        $this->expectException(InvalidNewsletterValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        SendWindow::of($days, $from, $until, $timezone);
    }

    /**
     * @return iterable<string, array{list<int>, string, string, string, string}>
     */
    public static function refused(): iterable
    {
        yield 'no day' => [[], '09:00', '17:00', 'UTC', 'The days of a send window are ISO days, 1 for Monday to 7 for Sunday, at least one.'];
        yield 'day 0' => [[0, 1], '09:00', '17:00', 'UTC', 'The days of a send window are ISO days'];
        yield 'day 8' => [[8], '09:00', '17:00', 'UTC', 'The days of a send window are ISO days'];
        yield 'a bad hour' => [[1], '9:00', '17:00', 'UTC', '"9:00" is not an hour of a send window: HH:MM, from 00:00 to 24:00.'];
        yield 'hour 24:01' => [[1], '09:00', '24:01', 'UTC', '"24:01" is not an hour'];
        yield 'minute 60' => [[1], '09:60', '17:00', 'UTC', '"09:60" is not an hour'];
        yield 'empty' => [[1], '09:00', '09:00', 'UTC', 'A send window closes after it opens: 09:00 is not after 09:00.'];
        yield 'reversed' => [[1], '22:00', '06:00', 'UTC', 'A send window closes after it opens'];
        yield 'a bad zone' => [[1], '09:00', '17:00', 'Mars/Base', '"Mars/Base" is not a time zone.'];
    }
}
