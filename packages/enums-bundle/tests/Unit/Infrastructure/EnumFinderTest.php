<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Infrastructure;

use Phil\EnumsBundle\Infrastructure\Discovery\EnumFinder;
use Phil\EnumsBundle\Tests\Unit\Finder\Ghost;
use Phil\EnumsBundle\Tests\Unit\Finder\Nested\Spirit;
use Phil\EnumsBundle\Tests\Unit\Fixture\Access;
use Phil\EnumsBundle\Tests\Unit\Fixture\Delivery;
use Phil\EnumsBundle\Tests\Unit\Fixture\Level;
use Phil\EnumsBundle\Tests\Unit\Fixture\NoCase;
use Phil\EnumsBundle\Tests\Unit\Fixture\OrderStatus;
use Phil\EnumsBundle\Tests\Unit\Fixture\Shape;
use Phil\EnumsBundle\Tests\Unit\Fixture\TicketKind;
use Phil\EnumsBundle\Tests\Unit\Pair\Left;
use Phil\EnumsBundle\Tests\Unit\Pair\Right;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnumFinder::class)]
final class EnumFinderTest extends TestCase
{
    private const string FINDER = __DIR__.'/../Finder';

    public function testItFindsTheAutoloadedEnumsOfADirectoryAndItsSubdirectories(): void
    {
        self::assertSame([Ghost::class, Spirit::class], EnumFinder::find(self::FINDER));
    }

    public function testItReadsFilesAndSeveralPathsEachEnumOnceSorted(): void
    {
        self::assertSame(
            [Ghost::class, Spirit::class, Access::class, Delivery::class, Level::class, NoCase::class, OrderStatus::class, Shape::class, TicketKind::class],
            EnumFinder::find([__DIR__.'/../Fixture', self::FINDER.'/Ghost.php', self::FINDER]),
        );
    }

    public function testAFileThatIsNotPhpOrAMissingPathGivesNothing(): void
    {
        self::assertSame([], EnumFinder::find([self::FINDER.'/notes.txt', self::FINDER.'/missing', self::FINDER.'/NotAnEnum.php']));
    }

    public function testAPhpFileGivenAloneIsRead(): void
    {
        self::assertSame([Ghost::class], EnumFinder::find(self::FINDER.'/Ghost.php'));
    }

    public function testEveryEnumOfAFileIsFound(): void
    {
        require_once __DIR__.'/../Pair/pair.php';

        self::assertSame([Left::class, Right::class], EnumFinder::find(__DIR__.'/../Pair/pair.php'));
    }

    public function testAnEnumNoAutoloaderFindsIsLeftOut(): void
    {
        self::assertSame([], EnumFinder::find(self::FINDER.'/lost.php'));
    }
}
