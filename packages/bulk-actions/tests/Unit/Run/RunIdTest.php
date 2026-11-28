<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Unit\Run;

use Phil\BulkActions\Exception\InvalidInput;
use Phil\BulkActions\Run\ItemContext;
use Phil\BulkActions\Run\RunId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RunId::class)]
#[CoversClass(ItemContext::class)]
#[CoversClass(InvalidInput::class)]
final class RunIdTest extends TestCase
{
    public function testARunIdIsSixteenRandomBytesInHexadecimal(): void
    {
        $id = RunId::generate();

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $id->value);
        self::assertNotSame($id->value, RunId::generate()->value);
        self::assertSame($id->value, RunId::fromString($id->value)->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notIds(): iterable
    {
        yield 'empty' => [''];
        yield 'capitals' => [str_repeat('A', 32)];
        yield '31 digits' => [str_repeat('a', 31)];
        yield '33 digits' => [str_repeat('a', 33)];
        yield 'a final line feed' => [str_repeat('a', 32)."\n"];
        yield 'a g' => [str_repeat('a', 31).'g'];
    }

    #[DataProvider('notIds')]
    public function testWhatIsNotARunIdIsRefused(string $value): void
    {
        $this->expectExceptionObject(InvalidInput::because('run identifier', 'must be 32 lowercase hexadecimal digits'));

        RunId::fromString($value);
    }

    public function testTheIdempotencyKeyOfAnItemIsTheSameForEveryAttempt(): void
    {
        $run = RunId::fromString('0123456789abcdef0123456789abcdef');
        $first = new ItemContext($run, 'p1', 'ana', 1);
        $retry = new ItemContext($run, 'p1', 'bob', 2);

        self::assertSame('bulk:0123456789abcdef0123456789abcdef:'.hash('sha256', 'p1'), $first->idempotencyKey());
        self::assertSame($first->idempotencyKey(), $retry->idempotencyKey());
        self::assertNotSame($first->idempotencyKey(), new ItemContext($run, 'p2', 'ana', 1)->idempotencyKey());
        self::assertSame('p1', $first->item);
        self::assertSame('ana', $first->actor);
        self::assertSame(1, $first->attempt);
        self::assertSame($run, $first->run);
    }
}
