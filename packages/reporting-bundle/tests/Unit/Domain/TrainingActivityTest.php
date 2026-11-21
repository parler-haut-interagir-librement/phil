<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Domain;

use Closure;
use Phil\ReportingBundle\Domain\Exception\InvalidFigures;
use Phil\ReportingBundle\Domain\Training\TrainingActivity;
use Phil\ReportingBundle\Domain\Training\UnitNode;
use Phil\ReportingBundle\Tests\Unit\Fixture\Trainings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TrainingActivity::class)]
#[CoversClass(UnitNode::class)]
final class TrainingActivityTest extends TestCase
{
    public function testAnActivityAtItsBounds(): void
    {
        $least = new TrainingActivity(Trainings::on('2026-03-01'), 0);
        self::assertSame([0, 0, 0, null], [$least->hundredthsOfHours, $least->cost, $least->eligibleCost, $least->unit]);
        self::assertEquals(Trainings::on('2026-03-01'), $least->on);

        $most = new TrainingActivity(Trainings::on('2026-03-01'), 1_000_000, 1_000_000_000_000, 1_000_000_000_000, str_repeat('~', 128));
        self::assertSame([1_000_000, 1_000_000_000_000, 1_000_000_000_000, str_repeat('~', 128)], [$most->hundredthsOfHours, $most->cost, $most->eligibleCost, $most->unit]);
        self::assertSame('!', new TrainingActivity(Trainings::on('2026-03-01'), 1, unit: '!')->unit);
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function invalidActivities(): iterable
    {
        $on = Trainings::on('2026-03-01');

        yield 'negative hours' => [static fn (): TrainingActivity => new TrainingActivity($on, -1), 'the hours of a training activity are from 0 to 1000000 hundredths'];
        yield 'too many hours' => [static fn (): TrainingActivity => new TrainingActivity($on, 1_000_001), 'the hours of a training activity are from 0 to 1000000 hundredths'];
        yield 'a negative cost' => [static fn (): TrainingActivity => new TrainingActivity($on, 1, -1), 'the cost of a training activity is from 0 to 1000000000000'];
        yield 'too large a cost' => [static fn (): TrainingActivity => new TrainingActivity($on, 1, 1_000_000_000_001), 'the cost of a training activity is from 0 to 1000000000000'];
        yield 'a negative eligible cost' => [static fn (): TrainingActivity => new TrainingActivity($on, 1, 10, -1), 'the eligible cost of a training activity is from 0 to its cost'];
        yield 'an eligible cost above the cost' => [static fn (): TrainingActivity => new TrainingActivity($on, 1, 10, 11), 'the eligible cost of a training activity is from 0 to its cost'];
        yield 'an empty unit' => [static fn (): TrainingActivity => new TrainingActivity($on, 1, unit: ''), 'a unit is named by 1 to 128 printable ASCII characters'];
        yield 'too long a unit' => [static fn (): TrainingActivity => new TrainingActivity($on, 1, unit: str_repeat('a', 129)), 'a unit is named by 1 to 128 printable ASCII characters'];
        yield 'a unit with a space' => [static fn (): TrainingActivity => new TrainingActivity($on, 1, unit: 'a b'), 'a unit is named by 1 to 128 printable ASCII characters'];
        yield 'a unit with a line after it' => [static fn (): TrainingActivity => new TrainingActivity($on, 1, unit: "ab\n"), 'a unit is named by 1 to 128 printable ASCII characters'];
        yield 'a unit with a line before it' => [static fn (): TrainingActivity => new TrainingActivity($on, 1, unit: "\nab"), 'a unit is named by 1 to 128 printable ASCII characters'];
        yield 'a unit with a delete' => [static fn (): TrainingActivity => new TrainingActivity($on, 1, unit: "a\x7F"), 'a unit is named by 1 to 128 printable ASCII characters'];
        yield 'an empty unit node' => [static fn (): UnitNode => new UnitNode('', 'Sales'), 'a unit is named by 1 to 128 printable ASCII characters'];
        yield 'too long a unit node' => [static fn (): UnitNode => new UnitNode(str_repeat('a', 129), 'Sales'), 'a unit is named by 1 to 128 printable ASCII characters'];
        yield 'a unit node with a line before it' => [static fn (): UnitNode => new UnitNode("\nab", 'Sales'), 'a unit is named by 1 to 128 printable ASCII characters'];
        yield 'a unit node with a line after it' => [static fn (): UnitNode => new UnitNode("ab\n", 'Sales'), 'a unit is named by 1 to 128 printable ASCII characters'];
        yield 'an empty name' => [static fn (): UnitNode => new UnitNode('sales', ''), 'the name of a unit has 1 to 255 characters, none of them a control character'];
        yield 'too long a name' => [static fn (): UnitNode => new UnitNode('sales', str_repeat('é', 256)), 'the name of a unit has 1 to 255 characters, none of them a control character'];
        yield 'a name with a tab' => [static fn (): UnitNode => new UnitNode('sales', "Sa\tles"), 'the name of a unit has 1 to 255 characters, none of them a control character'];
        yield 'a name with a line after it' => [static fn (): UnitNode => new UnitNode('sales', "Sales\n"), 'the name of a unit has 1 to 255 characters, none of them a control character'];
        yield 'a name with a line before it' => [static fn (): UnitNode => new UnitNode('sales', "\nSales"), 'the name of a unit has 1 to 255 characters, none of them a control character'];
        yield 'a name that is not UTF-8' => [static fn (): UnitNode => new UnitNode('sales', "\xE9"), 'the name of a unit has 1 to 255 characters, none of them a control character'];
    }

    /**
     * @param Closure(): mixed $create
     */
    #[DataProvider('invalidActivities')]
    public function testAnActivityOrAUnitOutOfShapeIsRefused(Closure $create, string $problem): void
    {
        $this->expectExceptionObject(InvalidFigures::because($problem));

        $create();
    }

    public function testAUnitNode(): void
    {
        $node = new UnitNode(str_repeat('~', 128), str_repeat('é', 255));

        self::assertSame([str_repeat('~', 128), str_repeat('é', 255)], [$node->id, $node->name]);
        self::assertSame(['!', 'S'], [new UnitNode('!', 'S')->id, new UnitNode('!', 'S')->name]);
    }
}
