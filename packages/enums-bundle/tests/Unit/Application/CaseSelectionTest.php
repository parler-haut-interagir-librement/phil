<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Application;

use InvalidArgumentException;
use Phil\Enums\Attribute\Label;
use Phil\EnumsBundle\Application\Selection\CaseSelection;
use Phil\EnumsBundle\Tests\Unit\Fixture\OrderStatus;
use Phil\EnumsBundle\Tests\Unit\Fixture\Priority;
use Phil\EnumsBundle\Tests\Unit\Fixture\Shape;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(CaseSelection::class)]
final class CaseSelectionTest extends TestCase
{
    public function testWithoutCriterionEveryCaseIsSelected(): void
    {
        $selection = new CaseSelection(OrderStatus::class);

        self::assertSame(OrderStatus::cases(), $selection->cases()->all());
        self::assertFalse($selection->narrows());
    }

    public function testOnlyKeepsTheGivenCasesInDeclarationOrder(): void
    {
        $selection = new CaseSelection(OrderStatus::class, only: [OrderStatus::CANCELLED, OrderStatus::PENDING]);

        self::assertSame([OrderStatus::PENDING, OrderStatus::CANCELLED], $selection->cases()->all());
        self::assertTrue($selection->narrows());
    }

    public function testExceptLeavesTheGivenCasesOut(): void
    {
        $selection = new CaseSelection(OrderStatus::class, except: [OrderStatus::REFUNDED]);

        self::assertSame([OrderStatus::PENDING, OrderStatus::IN_TRANSIT, OrderStatus::CANCELLED], $selection->cases()->all());
        self::assertTrue($selection->narrows());
    }

    public function testGroupsKeepTheCasesInAnyOfThem(): void
    {
        self::assertSame([OrderStatus::PENDING, OrderStatus::IN_TRANSIT], new CaseSelection(OrderStatus::class, groups: ['customer'])->cases()->all());
        self::assertSame([OrderStatus::PENDING, OrderStatus::CANCELLED], new CaseSelection(OrderStatus::class, groups: ['staff'])->cases()->all());
        self::assertTrue(new CaseSelection(OrderStatus::class, groups: ['staff'])->narrows());
    }

    public function testWhereMetaKeepsTheCasesWithEveryValue(): void
    {
        $selection = new CaseSelection(OrderStatus::class, groups: ['staff'], whereMeta: [Priority::class => 2]);

        self::assertSame([OrderStatus::CANCELLED], $selection->cases()->all());
        self::assertTrue(new CaseSelection(OrderStatus::class, whereMeta: [Priority::class => 2])->narrows());
    }

    public function testTheCriteriaCombine(): void
    {
        $selection = new CaseSelection(OrderStatus::class, only: [OrderStatus::PENDING, OrderStatus::IN_TRANSIT, OrderStatus::CANCELLED], except: [OrderStatus::IN_TRANSIT], groups: ['customer']);

        self::assertSame([OrderStatus::PENDING], $selection->cases()->all());
    }

    public function testTheEnumMustExist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('"stdClass" is not an enum.');

        new CaseSelection(stdClass::class); // @phpstan-ignore argument.type
    }

    public function testACaseOfAnotherEnumIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The case '.Shape::class.'::Circle is not a case of '.OrderStatus::class.'.');

        new CaseSelection(OrderStatus::class, except: [Shape::Circle]);
    }

    public function testACaseOfAnotherEnumIsRefusedInOnlyToo(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CaseSelection(OrderStatus::class, only: [Shape::Circle]);
    }

    public function testOptionsAreReadIntoASelection(): void
    {
        $selection = CaseSelection::fromOptions(OrderStatus::class, [OrderStatus::PENDING, OrderStatus::CANCELLED], [OrderStatus::CANCELLED], 'customer', [Priority::class => 1]);

        self::assertSame([OrderStatus::PENDING], $selection->cases()->all());
        self::assertSame([OrderStatus::PENDING, OrderStatus::CANCELLED], $selection->only);
        self::assertSame([OrderStatus::CANCELLED], $selection->except);
        self::assertSame(['customer'], $selection->groups);
        self::assertSame([Priority::class => 1, Label::class => 'Pending'], CaseSelection::fromOptions(OrderStatus::class, whereMeta: [Priority::class => 1, Label::class => 'Pending'])->whereMeta);
        self::assertSame(['staff', 'customer'], CaseSelection::fromOptions(OrderStatus::class, groups: ['staff', 'customer'])->groups);
        self::assertSame(OrderStatus::cases(), CaseSelection::fromOptions(OrderStatus::class)->cases()->all());
    }

    /**
     * @return iterable<string, array{array<int, mixed>, string}>
     */
    public static function malformedOptions(): iterable
    {
        yield 'enum not a string' => [[5], '"int" is not an enum.'];
        yield 'enum not an enum' => [[stdClass::class], '"string" is not an enum.'];
        yield 'only not a list' => [[OrderStatus::class, 'pending'], '"only" is a list of enum cases.'];
        yield 'only with a value' => [[OrderStatus::class, ['pending']], '"only" is a list of enum cases.'];
        yield 'except with a value' => [[OrderStatus::class, [], [null]], '"except" is a list of enum cases.'];
        yield 'group not a string' => [[OrderStatus::class, [], [], 5], '"group" is a group name or a list of group names.'];
        yield 'group list with a number' => [[OrderStatus::class, [], [], ['staff', 5]], '"group" is a group name or a list of group names.'];
        yield 'where_meta not a map' => [[OrderStatus::class, [], [], [], 'priority'], '"where_meta" maps meta property classes to values.'];
        yield 'where_meta key not a meta property' => [[OrderStatus::class, [], [], [], [stdClass::class => 1]], '"where_meta" maps meta property classes to values.'];
        yield 'where_meta key a number' => [[OrderStatus::class, [], [], [], [1]], '"where_meta" maps meta property classes to values.'];
    }

    /**
     * @param array<int, mixed> $arguments
     */
    #[DataProvider('malformedOptions')]
    public function testMalformedOptionsAreRefused(array $arguments, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($message);

        CaseSelection::fromOptions(...$arguments);
    }
}
