<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Phil\AccessBundle\Domain\Exception\InvalidAccess;
use Phil\AccessBundle\Infrastructure\Dbal\DbalTuplePartitions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The rule for the name of the table, which ends the pattern at the very end of the name, not before a final newline.
 */
#[CoversClass(DbalTuplePartitions::class)]
final class DbalTuplePartitionsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function badTableNames(): iterable
    {
        yield 'a final newline' => ["tuples\n"];
        yield 'an injection' => ['tuples; DROP TABLE x'];
        yield 'a capital' => ['Tuples'];
        yield 'a leading digit' => ['1tuples'];
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('t', 64)];
    }

    #[DataProvider('badTableNames')]
    public function testATableNameFollowsTheRuleOfTheStore(string $table): void
    {
        $this->expectException(InvalidAccess::class);
        $this->expectExceptionMessageIs(\sprintf('"%s" is not a table name: up to 63 lowercase letters, digits or "_", not starting with a digit.', $table));

        new DbalTuplePartitions(self::createStub(Connection::class), $table);
    }

    public function testTheLongestNameIsKept(): void
    {
        self::assertInstanceOf(DbalTuplePartitions::class, new DbalTuplePartitions(self::createStub(Connection::class), '_'.str_repeat('t', 61).'9'));
    }
}
