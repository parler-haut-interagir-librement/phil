<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Breach;

use Phil\Passwords\Breach\InMemoryBreachSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryBreachSource::class)]
final class InMemoryBreachSourceTest extends TestCase
{
    public function testFromPasswords(): void
    {
        $source = InMemoryBreachSource::fromPasswords(['password' => 7, 123456 => 9]);

        self::assertSame(7, $source->occurrences('password'));
        self::assertSame(9, $source->occurrences('123456'));
        self::assertSame(0, $source->occurrences('Password'));
    }

    public function testFromHashesInAnyCase(): void
    {
        $source = InMemoryBreachSource::fromHashes(['5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8' => 4]);

        self::assertSame(4, $source->occurrences('password'));
        self::assertSame(0, $source->occurrences('other'));
    }
}
