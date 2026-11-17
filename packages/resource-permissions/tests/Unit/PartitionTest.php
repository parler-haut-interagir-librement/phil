<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tests\Unit;

use Phil\ResourcePermissions\Exception\InvalidRelation;
use Phil\ResourcePermissions\Exception\UnscopedTuples;
use Phil\ResourcePermissions\FixedPartition;
use Phil\ResourcePermissions\Partition;
use Phil\ResourcePermissions\SinglePartition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Partition::class)]
#[CoversClass(SinglePartition::class)]
#[CoversClass(FixedPartition::class)]
#[CoversClass(UnscopedTuples::class)]
final class PartitionTest extends TestCase
{
    public function testTheDefaultPartitionIsEmpty(): void
    {
        self::assertSame('', Partition::DEFAULT);
        self::assertSame('', Partition::check(''));
        self::assertSame('', new SinglePartition()->partition());
    }

    public function testANamedPartitionIsKeptAsItIs(): void
    {
        self::assertSame('Tenant-é 2', Partition::check('Tenant-é 2'));
        self::assertSame('retail', new FixedPartition('retail')->partition());
        self::assertSame(str_repeat('a', 128), Partition::check(str_repeat('a', 128)));
        self::assertSame(str_repeat('é', 64), Partition::check(str_repeat('é', 64)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalid(): iterable
    {
        yield 'too long' => [str_repeat('a', 129), 'A partition is at most 128 bytes; this one is 129.'];
        yield 'too long in bytes' => [str_repeat('é', 64).'a', 'A partition is at most 128 bytes; this one is 129.'];
        yield 'a line break' => ["a\nb", 'A partition is UTF-8 without control characters.'];
        yield 'a final line break' => ["ab\n", 'A partition is UTF-8 without control characters.'];
        yield 'a nul byte' => ["a\0", 'A partition is UTF-8 without control characters.'];
        yield 'a C1 control' => ["a\u{85}", 'A partition is UTF-8 without control characters.'];
        yield 'invalid UTF-8' => ["a\xC3", 'A partition is UTF-8 without control characters.'];
    }

    #[DataProvider('invalid')]
    public function testAPartitionAgainstTheRuleIsRefused(string $partition, string $message): void
    {
        $this->expectException(InvalidRelation::class);
        $this->expectExceptionMessageIs($message);

        Partition::check($partition);
    }

    public function testAFixedPartitionAgainstTheRuleIsRefused(): void
    {
        $this->expectException(InvalidRelation::class);

        new FixedPartition("a\tb");
    }

    public function testAnUnscopedCallSaysWhy(): void
    {
        self::assertSame('No tenant.', UnscopedTuples::because('No tenant.')->getMessage());
    }
}
