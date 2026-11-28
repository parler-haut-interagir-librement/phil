<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Unit\Selection;

use Phil\BulkActions\Exception\InvalidInput;
use Phil\BulkActions\Internal\Check;
use Phil\BulkActions\Selection\FilterCriteria;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(FilterCriteria::class)]
#[CoversClass(Check::class)]
#[CoversClass(InvalidInput::class)]
final class FilterCriteriaTest extends TestCase
{
    public function testItKeepsTheParametersSortedByName(): void
    {
        $filter = FilterCriteria::of('grid', ['status' => 'draft', 'category' => [3, 'x', null], 'price' => 1.5, 'on' => false, 'none' => null]);

        self::assertSame('grid', $filter->name);
        self::assertSame(['category' => [3, 'x', null], 'none' => null, 'on' => false, 'price' => 1.5, 'status' => 'draft'], $filter->parameters);
        self::assertSame(['name' => 'grid', 'parameters' => $filter->parameters], $filter->toArray());
    }

    public function testItReadsBackWhatItStored(): void
    {
        $filter = FilterCriteria::of('grid', ['status' => 'draft', 'page' => 2]);

        self::assertEquals($filter, FilterCriteria::fromArray($filter->toArray()));
        self::assertEquals(FilterCriteria::of('grid'), FilterCriteria::fromArray(['name' => 'grid']));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function storedFilters(): iterable
    {
        yield 'no name' => [['parameters' => []]];
        yield 'a name that is not a string' => [['name' => 3]];
        yield 'parameters that are not an array' => [['name' => 'grid', 'parameters' => 'x']];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('storedFilters')]
    public function testItRefusesAStoredFilterItCannotHaveWritten(array $data): void
    {
        $this->expectExceptionObject(InvalidInput::because('stored filter', 'must have a name and parameters'));

        FilterCriteria::fromArray($data);
    }

    public function testItAcceptsThirtyTwoParametersAndNotMore(): void
    {
        $parameters = [];

        for ($i = 0; $i < FilterCriteria::MAX_PARAMETERS; ++$i) {
            $parameters['p'.$i] = $i;
        }

        self::assertCount(32, FilterCriteria::of('grid', $parameters)->parameters);

        $parameters['extra'] = 1;
        $this->expectExceptionObject(InvalidInput::because('filter', 'must have 32 parameters at most'));
        FilterCriteria::of('grid', $parameters);
    }

    public function testItBoundsTheEncodedSize(): void
    {
        $value = str_repeat('a', 16_000);
        self::assertSame($value, FilterCriteria::of('grid', ['q' => $value])->parameters['q']);

        $this->expectExceptionObject(InvalidInput::because('filter', 'must have 16384 bytes at most once encoded'));
        FilterCriteria::of('grid', ['q' => $value, 'r' => str_repeat('b', 400)]);
    }

    public function testTheBoundIsSixteenKibibytesExactly(): void
    {
        // {"name":"grid","parameters":{"q":""}} is 37 bytes.
        self::assertCount(1, FilterCriteria::of('grid', ['q' => str_repeat('a', 16_384 - 37)])->parameters);

        $this->expectExceptionObject(InvalidInput::because('filter', 'must have 16384 bytes at most once encoded'));
        FilterCriteria::of('grid', ['q' => str_repeat('a', 16_384 - 36)]);
    }

    public function testTheBoundCountsTheEncodedBytesOfUnicodeAsIs(): void
    {
        // 5 400 "é" are 10 800 bytes as UTF-8, 32 400 once escaped as é: the bound counts the bytes written.
        self::assertCount(1, FilterCriteria::of('grid', ['q' => str_repeat('é', 5_400)])->parameters);
    }

    /**
     * @return iterable<string, array{string, array<array-key, mixed>, string}>
     */
    public static function invalidFilters(): iterable
    {
        yield 'an empty name' => ['', [], 'The filter name must be a letter followed by letters, digits, "_", "-" or ".", 64 bytes at most.'];
        yield 'a name starting with a digit' => ['1grid', [], 'The filter name must be a letter followed by letters, digits, "_", "-" or ".", 64 bytes at most.'];
        yield 'a name with a space' => ['my grid', [], 'The filter name must be a letter followed by letters, digits, "_", "-" or ".", 64 bytes at most.'];
        yield 'a name with a final line feed' => ["grid\n", [], 'The filter name must be a letter followed by letters, digits, "_", "-" or ".", 64 bytes at most.'];
        yield 'a name of 65 bytes' => [str_repeat('g', 65), [], 'The filter name must be a letter followed by letters, digits, "_", "-" or ".", 64 bytes at most.'];
        yield 'a numeric parameter name' => ['grid', [0 => 'x'], 'The filter parameter must be a letter followed by letters, digits, "_", "-" or ".", 64 bytes at most.'];
        yield 'an object' => ['grid', ['q' => new stdClass()], 'The filter parameter "q" must be a string, a number, a boolean, null or a list of them.'];
        yield 'a nested list' => ['grid', ['q' => [[1]]], 'The filter parameter "q" must be a string, a number, a boolean, null or a list of them.'];
        yield 'a map' => ['grid', ['q' => ['a' => 1]], 'The filter parameter "q" must be a list of 1000 values at most.'];
        yield 'a list too long' => ['grid', ['q' => range(0, 1_000)], 'The filter parameter "q" must be a list of 1000 values at most.'];
        yield 'an infinite number' => ['grid', ['q' => \INF], 'The filter parameter "q" must be a finite number.'];
        yield 'not a number' => ['grid', ['q' => \NAN], 'The filter parameter "q" must be a finite number.'];
        yield 'invalid UTF-8' => ['grid', ['q' => "\xC3\x28"], 'The filter parameter "q" must be valid UTF-8 of 65535 bytes at most.'];
        yield 'a string too long' => ['grid', ['q' => str_repeat('a', 65_536)], 'The filter parameter "q" must be valid UTF-8 of 65535 bytes at most.'];
    }

    /**
     * @param array<array-key, mixed> $parameters
     */
    #[DataProvider('invalidFilters')]
    public function testItRefusesWhatItCannotStore(string $name, array $parameters, string $message): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains($message);

        FilterCriteria::of($name, $parameters);
    }

    public function testItAcceptsTheLongestNameAndTheLongestList(): void
    {
        $name = 'g'.str_repeat('0', 63);

        self::assertSame($name, FilterCriteria::of($name)->name);
        self::assertSame('a.b-c_D9', FilterCriteria::of('a.b-c_D9')->name);
        self::assertSame(range(1, 1_000), FilterCriteria::of('grid', ['q' => range(1, 1_000)])->parameters['q'] ?? null);
    }
}
