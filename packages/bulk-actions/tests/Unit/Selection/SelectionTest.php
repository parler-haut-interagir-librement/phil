<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Unit\Selection;

use Phil\BulkActions\Exception\InvalidInput;
use Phil\BulkActions\Internal\Check;
use Phil\BulkActions\Selection\FilterCriteria;
use Phil\BulkActions\Selection\Selection;
use Phil\BulkActions\Selection\SelectionKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Selection::class)]
#[CoversClass(SelectionKind::class)]
#[CoversClass(FilterCriteria::class)]
#[CoversClass(Check::class)]
#[CoversClass(InvalidInput::class)]
final class SelectionTest extends TestCase
{
    public function testIdentifiersAreStringsWithoutDuplicatesInTheOrderGiven(): void
    {
        $selection = Selection::identifiers([42, 'b', '42', 'a', 'b'], [42 => 3, 'a' => 'v7']);

        self::assertSame(SelectionKind::Identifiers, $selection->kind);
        self::assertSame(['42', 'b', 'a'], $selection->identifiers);
        self::assertSame(['42' => '3', 'a' => 'v7'], $selection->versions);
        self::assertNull($selection->filter);
        self::assertSame([], $selection->excluded);
        self::assertSame('3', $selection->versionOf('42'));
        self::assertSame('v7', $selection->versionOf('a'));
        self::assertNull($selection->versionOf('b'));
    }

    public function testAnEmptySelectionIsRefused(): void
    {
        $this->expectExceptionObject(InvalidInput::because('selection', 'must name at least one item'));

        Selection::identifiers([]);
    }

    public function testAVersionMustBelongToASelectedItem(): void
    {
        $this->expectExceptionObject(InvalidInput::because('version of "c"', 'must belong to a selected item'));

        Selection::identifiers(['a'], ['c' => 1]);
    }

    public function testAVersionIsChecked(): void
    {
        $this->expectExceptionObject(InvalidInput::because('version', 'must have 1 to 191 bytes and no control character'));

        Selection::identifiers(['a'], ['a' => '']);
    }

    /**
     * @return iterable<string, array{int|string}>
     */
    public static function invalidIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield '192 bytes' => [str_repeat('x', 192)];
        yield 'a line feed' => ["a\nb"];
        yield 'a null byte' => ["a\0"];
        yield 'a delete' => ["a\x7F"];
        yield 'a unit separator' => ["a\x1F"];
    }

    #[DataProvider('invalidIdentifiers')]
    public function testAnIdentifierIsBounded(int|string $identifier): void
    {
        $this->expectExceptionObject(InvalidInput::because('identifier', 'must have 1 to 191 bytes and no control character'));

        Selection::identifiers([$identifier]);
    }

    public function testTheLongestIdentifierAndASpaceAreAccepted(): void
    {
        self::assertSame([str_repeat('x', 191), 'a b', '0'], Selection::identifiers([str_repeat('x', 191), 'a b', 0])->identifiers);
    }

    public function testASelectionListsTenThousandIdentifiersAtMost(): void
    {
        self::assertCount(10_000, Selection::identifiers(range(1, 10_000))->identifiers);

        $this->expectExceptionObject(InvalidInput::because('selection', 'must list 10000 identifiers at most'));
        Selection::identifiers(range(1, 10_001));
    }

    public function testAFilterKeepsItsUntickedItems(): void
    {
        $filter = FilterCriteria::of('status', ['status' => 'draft']);
        $selection = Selection::filter($filter, [7, 'x', 7]);

        self::assertSame(SelectionKind::Filter, $selection->kind);
        self::assertSame($filter, $selection->filter);
        self::assertSame(['7', 'x'], $selection->excluded);
        self::assertSame([], $selection->identifiers);
        self::assertSame([], $selection->versions);
        self::assertSame([], Selection::filter($filter)->excluded);
    }

    public function testAnUntickedIdentifierIsChecked(): void
    {
        $this->expectExceptionObject(InvalidInput::because('excluded identifier', 'must have 1 to 191 bytes and no control character'));

        Selection::filter(FilterCriteria::of('status'), ['']);
    }

    public function testItReadsBackWhatItStored(): void
    {
        $identifiers = Selection::identifiers(['a', 2], ['a' => 4]);
        $filter = Selection::filter(FilterCriteria::of('status', ['status' => 'draft']), ['z']);

        self::assertSame(['kind' => 'identifiers', 'identifiers' => ['a', '2'], 'versions' => ['a' => '4'], 'filter' => null, 'excluded' => []], $identifiers->toArray());
        self::assertSame(['kind' => 'filter', 'identifiers' => [], 'versions' => [], 'filter' => ['name' => 'status', 'parameters' => ['status' => 'draft']], 'excluded' => ['z']], $filter->toArray());
        self::assertEquals($identifiers, Selection::fromArray($identifiers->toArray()));
        self::assertEquals($filter, Selection::fromArray($filter->toArray()));
        self::assertEquals(Selection::identifiers([5]), Selection::fromArray(['kind' => 'identifiers', 'identifiers' => [5]]));
        self::assertEquals(Selection::filter(FilterCriteria::of('status')), Selection::fromArray(['kind' => 'filter', 'filter' => ['name' => 'status']]));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function storedSelections(): iterable
    {
        $lists = 'The stored selection must have a kind and lists of identifiers.';

        yield 'no kind' => [['identifiers' => ['a']], $lists];
        yield 'a kind that is not a string' => [['kind' => 1, 'identifiers' => ['a']], $lists];
        yield 'an unknown kind' => [['kind' => 'all', 'identifiers' => ['a']], $lists];
        yield 'identifiers that are not a list' => [['kind' => 'identifiers', 'identifiers' => ['x' => 'a']], $lists];
        yield 'identifiers that are not an array' => [['kind' => 'identifiers', 'identifiers' => 'a'], $lists];
        yield 'an identifier that is a float' => [['kind' => 'identifiers', 'identifiers' => [1.5]], $lists];
        yield 'unticked items that are not a list' => [['kind' => 'filter', 'filter' => ['name' => 's'], 'excluded' => 'a'], $lists];
        yield 'versions that are not an array' => [['kind' => 'identifiers', 'identifiers' => ['a'], 'versions' => 'x'], $lists];
        yield 'a filter missing' => [['kind' => 'filter'], 'The stored selection must have its filter.'];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('storedSelections')]
    public function testItRefusesAStoredSelectionItCannotHaveWritten(array $data, string $message): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains($message);

        Selection::fromArray($data);
    }
}
