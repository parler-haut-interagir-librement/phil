<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Domain\Placement;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Domain\Placement\Placement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Placement::class)]
final class PlacementTest extends TestCase
{
    public function testTheSharedPlacementNamesNoDatabase(): void
    {
        $placement = Placement::shared();

        self::assertNull($placement->database);
        self::assertTrue($placement->isShared());
        self::assertFalse($placement->isDedicated());
        self::assertSame('shared', $placement->toString());
    }

    public function testADedicatedPlacementNamesItsDatabase(): void
    {
        $placement = Placement::dedicated('tenant_retail_2');

        self::assertSame('tenant_retail_2', $placement->database);
        self::assertFalse($placement->isShared());
        self::assertTrue($placement->isDedicated());
        self::assertSame('tenant_retail_2', $placement->toString());
    }

    public function testPlacementsAreEqualWhenTheyNameTheSameDatabase(): void
    {
        self::assertTrue(Placement::shared()->equals(Placement::shared()));
        self::assertTrue(Placement::dedicated('a')->equals(Placement::dedicated('a')));
        self::assertFalse(Placement::dedicated('a')->equals(Placement::dedicated('b')));
        self::assertFalse(Placement::dedicated('a')->equals(Placement::shared()));
        self::assertFalse(Placement::shared()->equals(Placement::dedicated('a')));
    }

    public function testANameOfTheLongestLengthIsKept(): void
    {
        $name = 'a'.str_repeat('b', Placement::MAX_DATABASE_LENGTH - 1);

        self::assertSame($name, Placement::dedicated($name)->database);
    }

    public function testALongerNameIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A database name is at most 63 bytes; this one is 64.');

        Placement::dedicated('a'.str_repeat('b', Placement::MAX_DATABASE_LENGTH));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notADatabaseName(): iterable
    {
        yield 'empty' => [''];
        yield 'upper case' => ['Retail'];
        yield 'leading digit' => ['1retail'];
        yield 'leading underscore' => ['_retail'];
        yield 'hyphen' => ['re-tail'];
        yield 'dot' => ['re.tail'];
        yield 'quote' => ['retail"; DROP DATABASE x; --'];
        yield 'space' => ['re tail'];
        yield 'trailing line break' => ["retail\n"];
        yield 'accented letter' => ['détail'];
    }

    #[DataProvider('notADatabaseName')]
    public function testANameThatIsNotADatabaseNameIsRefused(string $name): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('is lowercase ASCII letters, digits and underscores, starting with a letter.');

        Placement::dedicated($name);
    }

    public function testTheRefusalEscapesWhatItCannotPrint(): void
    {
        $this->expectExceptionMessageIsOrContains('The database name "re\ntail\303\251"');

        Placement::dedicated("re\ntail\u{e9}");
    }
}
