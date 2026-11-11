<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Infrastructure;

use InvalidArgumentException;
use Phil\EnumsBundle\Infrastructure\Workflow\EnumWorkflows;
use Phil\EnumsBundle\Tests\Unit\Fixture\Access;
use Phil\EnumsBundle\Tests\Unit\Fixture\Delivery;
use Phil\EnumsBundle\Tests\Unit\Fixture\Level;
use Phil\EnumsBundle\Tests\Unit\Fixture\Shape;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnumWorkflows::class)]
final class EnumWorkflowsTest extends TestCase
{
    public function testTheStateMachineIsTheEnumsTransitions(): void
    {
        self::assertSame([
            'type' => 'state_machine',
            'supports' => [self::class],
            'marking_store' => ['type' => 'method', 'property' => 'delivery'],
            'places' => ['ordered', 'shipped', 'delivered', 'cancelled'],
            'initial_marking' => ['ordered'],
            'transitions' => [
                'ordered_to_shipped' => ['from' => ['ordered'], 'to' => ['shipped']],
                'ordered_to_cancelled' => ['from' => ['ordered'], 'to' => ['cancelled']],
                'shipped_to_delivered' => ['from' => ['shipped'], 'to' => ['delivered']],
            ],
        ], EnumWorkflows::definition(Delivery::class, [self::class], 'delivery'));
    }

    public function testTheValuesOfAnIntegerEnumAreWrittenAsStrings(): void
    {
        $definition = EnumWorkflows::definition(Level::class, [self::class], 'level');

        self::assertSame(['1', '2'], $definition['places']);
        self::assertSame(['2_to_1' => ['from' => ['2'], 'to' => ['1']]], $definition['transitions']);
        self::assertSame(['2'], $definition['initial_marking']);
    }

    public function testAPureEnumHasNoWorkflow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('A workflow marks its subject with backed cases; '.Shape::class.' is not a backed enum.');

        EnumWorkflows::definition(Shape::class, [], 'shape');
    }

    public function testAnEnumWithoutTransitionsHasNoWorkflow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(Access::class.' declares no #[TransitionsTo]: it has no workflow.');

        EnumWorkflows::definition(Access::class, [], 'access');
    }
}
