<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Unit\Change;

use Phil\BulkActions\Change\ChangeSet;
use Phil\BulkActions\Exception\InvalidInput;
use Phil\BulkActions\Internal\Check;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChangeSet::class)]
#[CoversClass(Check::class)]
#[CoversClass(InvalidInput::class)]
final class ChangeSetTest extends TestCase
{
    public function testATickedFieldSetToAnEmptyValueIsToldApartFromAnUntickedOne(): void
    {
        $changes = ChangeSet::fromSubmission(
            ['name' => '', 'price' => '0', 'active' => false, 'note' => 'kept', 'tags' => ['a', 'b']],
            ['name' => '1', 'price' => 'on', 'active' => true, 'note' => '0', 'tags' => 'TRUE', 'missing' => '1', 'other' => 'yes'],
        );

        self::assertSame(['name' => '', 'price' => '0', 'active' => false, 'tags' => ['a', 'b'], 'missing' => null], $changes->values);
        self::assertSame(['name', 'price', 'active', 'tags', 'missing'], $changes->fields());
        self::assertTrue($changes->has('missing'));
        self::assertNull($changes->value('missing'));
        self::assertFalse($changes->has('note'));
        self::assertNull($changes->value('note'));
        self::assertFalse($changes->value('active'));
        self::assertSame('0', $changes->value('price'));
        self::assertFalse($changes->isEmpty());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function unticked(): iterable
    {
        yield 'false' => [false];
        yield 'an empty string' => [''];
        yield 'zero' => ['0'];
        yield 'off' => ['off'];
        yield 'an integer one' => [1];
        yield 'null' => [null];
        yield 'a list' => [['1']];
        yield 'one with a space' => [' 1'];
        yield 'one written as a decimal' => ['1.0'];
    }

    #[DataProvider('unticked')]
    public function testOnlyATrueBoxTicksAField(mixed $box): void
    {
        self::assertTrue(ChangeSet::fromSubmission(['name' => 'x'], ['name' => $box])->isEmpty());
    }

    public function testNoneIsEmpty(): void
    {
        self::assertTrue(ChangeSet::none()->isEmpty());
        self::assertSame([], ChangeSet::none()->fields());
        self::assertEquals(ChangeSet::none(), ChangeSet::of([]));
    }

    public function testItSetsSixtyFourFieldsAtMost(): void
    {
        $values = [];

        for ($i = 0; $i < ChangeSet::MAX_FIELDS; ++$i) {
            $values['f'.$i] = $i;
        }

        self::assertCount(64, ChangeSet::of($values)->values);

        $values['extra'] = 1;
        $this->expectExceptionObject(InvalidInput::because('change', 'must set 64 fields at most'));
        ChangeSet::of($values);
    }

    public function testAFieldNameIsChecked(): void
    {
        $this->expectExceptionObject(InvalidInput::because('field', 'must be a letter followed by letters, digits, "_", "-" or ".", 64 bytes at most'));

        ChangeSet::fromSubmission([], ['na me' => '1']);
    }

    public function testAValueIsChecked(): void
    {
        $this->expectExceptionObject(InvalidInput::because('value of "price"', 'must be a finite number'));

        ChangeSet::of(['price' => \INF]);
    }

    public function testTheLongestStringIsAccepted(): void
    {
        self::assertSame(str_repeat('a', 65_535), ChangeSet::of(['body' => str_repeat('a', 65_535)])->value('body'));
        self::assertSame(1.25, ChangeSet::of(['price' => 1.25])->value('price'));
        self::assertSame(-3, ChangeSet::of(['price' => -3])->value('price'));
    }
}
