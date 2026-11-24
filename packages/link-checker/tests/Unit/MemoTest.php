<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Tests\Unit;

use Phil\LinkChecker\Memo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Memo::class)]
final class MemoTest extends TestCase
{
    public function testWhatWasFoundIsFoundOnce(): void
    {
        $memo = new Memo(10);
        $calls = 0;
        $find = static function () use (&$calls): ?string {
            ++$calls;

            return null;
        };

        self::assertNull($memo->remember('result', 'a', $find));
        self::assertNull($memo->remember('result', 'a', $find));
        self::assertSame(1, $calls);
    }

    /**
     * @return callable(): int
     */
    private function finds(int $value): callable
    {
        return static fn (): int => $value;
    }

    public function testTheOldestEntryIsForgottenPastTheSize(): void
    {
        $memo = new Memo(2);
        $memo->remember('result', 'a', $this->finds(1));
        $memo->remember('result', 'b', $this->finds(2));

        self::assertSame(1, $memo->remember('result', 'a', $this->finds(9)));

        $memo->remember('result', 'c', $this->finds(3));

        self::assertSame(9, $memo->remember('result', 'a', $this->finds(9)));
        self::assertSame(3, $memo->remember('result', 'c', $this->finds(0)));
        // Only the oldest entry was forgotten when c came: b had been forgotten when a came back.
        self::assertSame(8, $memo->remember('result', 'b', $this->finds(8)));
    }

    public function testEachKindHasItsOwnEntriesAndSize(): void
    {
        $memo = new Memo(1);
        $memo->remember('result', 'a', $this->finds(1));
        $memo->remember('anchors', 'a', $this->finds(2));

        self::assertSame(1, $memo->remember('result', 'a', $this->finds(9)));
        self::assertSame(2, $memo->remember('anchors', 'a', $this->finds(9)));

        $memo->remember('result', '42', $this->finds(42));
        self::assertSame(42, $memo->remember('result', '42', $this->finds(0)));
        self::assertSame(9, $memo->remember('result', 'a', $this->finds(9)));
    }

    public function testOnlyTheOldestEntryIsForgotten(): void
    {
        $memo = new Memo(3);
        $memo->remember('result', 'a', $this->finds(1));
        $memo->remember('result', 'b', $this->finds(2));
        $memo->remember('result', 'c', $this->finds(3));
        $memo->remember('result', 'd', $this->finds(4));

        self::assertSame(2, $memo->remember('result', 'b', $this->finds(0)));
        self::assertSame(3, $memo->remember('result', 'c', $this->finds(0)));
        self::assertSame(4, $memo->remember('result', 'd', $this->finds(0)));
    }
}
