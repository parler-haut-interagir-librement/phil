<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\UxKit\Application\Festivities;
use Phil\UxKit\Infrastructure\Twig\SeasonalDecorExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SeasonalDecorExtension::class)]
final class SeasonalDecorExtensionTest extends TestCase
{
    public function testNoneWhileTheSeasonalDecorIsOff(): void
    {
        self::assertNull(new SeasonalDecorExtension()->decor('2026-10-31'));
    }

    public function testATextIsReadInTheTimeZoneOfTheConfiguration(): void
    {
        $zone = new DateTimeZone('Pacific/Auckland');
        $extension = new SeasonalDecorExtension(new Festivities(Festivities::DEFAULTS, $zone), $zone);

        self::assertSame('remembrance-day', $extension->decor('2026-11-11 00:30')?->key);
        self::assertNull($extension->decor(new DateTimeImmutable('2026-11-11 12:30', new DateTimeZone('UTC'))), 'noon past on November 11 in UTC is the 12th in Auckland');
    }

    public function testADateIsTakenAsTheInstantItIs(): void
    {
        $zone = new DateTimeZone('UTC');
        $extension = new SeasonalDecorExtension(new Festivities(Festivities::DEFAULTS, $zone));

        self::assertSame('halloween', $extension->decor(new DateTimeImmutable('2026-10-31 12:00', $zone))?->key);
        self::assertSame('halloween', $extension->decor('2026-10-31')?->key, 'UTC by default');
    }
}
