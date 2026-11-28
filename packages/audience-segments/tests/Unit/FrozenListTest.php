<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Tests\Unit;

use DateTimeImmutable;
use Phil\AudienceSegments\Exception\InvalidSegment;
use Phil\AudienceSegments\FrozenList;
use Phil\Rules\Exception\InvalidLimit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FrozenList::class)]
final class FrozenListTest extends TestCase
{
    private FrozenList $list;

    protected function setUp(): void
    {
        $this->list = $this->list(['a1', 'b2', 'c3', 'd4', 'e5']);
    }

    public function testCountsAndFindsItsMembers(): void
    {
        self::assertSame(5, $this->list->count());

        foreach (['a1', 'b2', 'c3', 'd4', 'e5'] as $member) {
            self::assertTrue($this->list->contains($member), $member);
        }

        foreach (['a0', 'b', 'c30', 'e6', 'z'] as $member) {
            self::assertFalse($this->list->contains($member), $member);
        }

        self::assertFalse($this->list([])->contains('a1'));
    }

    public function testReadsByPages(): void
    {
        self::assertSame(['a1', 'b2'], $this->list->page(null, 2));
        self::assertSame(['c3', 'd4'], $this->list->page('b2', 2));
        self::assertSame(['c3', 'd4'], $this->list->page('b3', 2));
        self::assertSame(['a1'], $this->list->page('a', 1));
        self::assertSame(['e5'], $this->list->page('d4', 1000));
        self::assertSame([], $this->list->page('e5', 2));
        self::assertSame([], $this->list->page('f', 2));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function badLimits(): iterable
    {
        yield 'none' => [0];
        yield 'too many' => [1001];
    }

    #[DataProvider('badLimits')]
    public function testRefusesAPageLimitOutOfRange(int $limit): void
    {
        $this->expectException(InvalidLimit::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('limit is %d; it must lie between 1 and 1000.', $limit));

        $this->list->page(null, $limit);
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function badMembers(): iterable
    {
        yield 'unsorted' => [['b', 'a'], 'The members of a frozen list are distinct identifiers in ascending byte order.'];
        yield 'twice the same' => [['a', 'a'], 'The members of a frozen list are distinct identifiers in ascending byte order.'];
        yield 'an address' => [['ana@example.com x'], 'is not a member identifier'];
    }

    /**
     * @param list<string> $members
     */
    #[DataProvider('badMembers')]
    public function testRefusesMembersOutOfForm(array $members, string $message): void
    {
        $this->expectException(InvalidSegment::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->list($members);
    }

    public function testRefusesASegmentOutOfForm(): void
    {
        $this->expectException(InvalidSegment::class);
        $this->expectExceptionMessageIsOrContains('is not a segment identifier');

        new FrozenList('', 'Name', 'f', new DateTimeImmutable('2026-11-21'), []);
    }

    /**
     * @param list<string> $members
     */
    private function list(array $members): FrozenList
    {
        return new FrozenList('press', 'Press', str_repeat('0', 64), new DateTimeImmutable('2026-11-21'), $members);
    }
}
