<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Tests\Unit\Domain;

use Phil\WellKnownBundle\Domain\Exception\InvalidValue;
use Phil\WellKnownBundle\Domain\ValueObject\GpcSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(GpcSupport::class)]
final class GpcSupportTest extends TestCase
{
    public function testTheDocumentSaysTheSiteHonoursTheSignal(): void
    {
        self::assertSame(['gpc' => true, 'lastUpdate' => '2026-11-21'], GpcSupport::since('2026-11-21')->toArray());
        self::assertSame('2028-02-29', GpcSupport::since('2028-02-29')->lastUpdate);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refused(): iterable
    {
        yield 'a day the calendar lacks' => ['2026-02-29'];
        yield 'a thirteenth month' => ['2026-13-01'];
        yield 'a day zero' => ['2026-11-00'];
        yield 'a date and a time' => ['2026-11-21T00:00:00Z'];
        yield 'a short month' => ['2026-1-21'];
        yield 'a space before' => [' 2026-11-21'];
        yield 'a new line after' => ["2026-11-21\n"];
        yield 'empty' => [''];
    }

    #[DataProvider('refused')]
    public function testOnlyAFullDateOfTheCalendarIsAccepted(string $date): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The last_update of gpc is an RFC 3339 full-date, such as "2026-11-21"');

        GpcSupport::since($date);
    }
}
