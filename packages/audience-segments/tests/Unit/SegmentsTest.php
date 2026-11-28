<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Tests\Unit;

use DateTimeImmutable;
use Phil\AudienceSegments\AudienceConditions;
use Phil\AudienceSegments\Contract\Audience;
use Phil\AudienceSegments\Exception\InvalidSegment;
use Phil\AudienceSegments\Exception\PortContractViolated;
use Phil\AudienceSegments\Exception\SegmentTooLarge;
use Phil\AudienceSegments\MemberPage;
use Phil\AudienceSegments\Membership;
use Phil\AudienceSegments\Segment;
use Phil\AudienceSegments\Segments;
use Phil\AudienceSegments\Tests\Support\FixedClock;
use Phil\AudienceSegments\Tests\Support\MemoryAudience;
use Phil\AudienceSegments\Tests\Support\MemoryExclusions;
use Phil\AudienceSegments\Tests\Support\MemoryFacts;
use Phil\Rules\Condition\Leaf;
use Phil\Rules\Exception\InvalidLimit;
use Phil\Rules\Json\RuleWriter;
use Phil\Rules\Rule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(Segments::class)]
#[CoversClass(MemberPage::class)]
#[CoversClass(Membership::class)]
#[CoversClass(PortContractViolated::class)]
#[CoversClass(SegmentTooLarge::class)]
final class SegmentsTest extends TestCase
{
    private Segment $press;

    protected function setUp(): void
    {
        $facts = new MemoryFacts();
        $facts->tags = ['m01' => ['press'], 'm02' => ['crew'], 'm03' => ['press'], 'm04' => ['press'], 'm05' => [], 'm06' => ['press'], 'm07' => ['press']];

        $this->press = new Segment('press', 'Press', new Rule(new Leaf('person.tagged', ['tags' => ['press']]), AudienceConditions::types(tags: $facts)));
    }

    public function testChecksOneMemberWithTheReason(): void
    {
        $segments = $this->segments(new MemoryAudience(), new MemoryExclusions('m03'));

        $in = $segments->check($this->press, 'm01');
        $out = $segments->check($this->press, 'm02');
        $excluded = $segments->check($this->press, 'm03');

        self::assertTrue($in->isMember());
        self::assertSame('m01', $in->member);
        self::assertFalse($in->excluded);
        self::assertFalse($out->isMember());
        self::assertSame('person.untagged', $out->result->trace->reason);
        self::assertFalse($excluded->isMember());
        self::assertTrue($excluded->excluded);
        self::assertTrue($excluded->result->passed);
    }

    public function testRefusesToCheckAnIdentifierOutOfForm(): void
    {
        $this->expectException(InvalidSegment::class);
        $this->expectExceptionMessageIsOrContains('is not a member identifier');

        $this->segments(new MemoryAudience(), new MemoryExclusions())->check($this->press, 'ana@example.com ');
    }

    public function testReadsADynamicSegmentByPagesWithoutTheExcluded(): void
    {
        $audience = new MemoryAudience('m07', 'm01', 'm02', 'm03', 'm04', 'm05', 'm06');
        $segments = $this->segments($audience, new MemoryExclusions('m04'), pageSize: 2);

        $first = $segments->page($this->press, null, 2);
        self::assertSame(['m01', 'm03'], $first->members);
        self::assertSame('m03', $first->next);

        $second = $segments->page($this->press, $first->next, 2);
        self::assertSame(['m06', 'm07'], $second->members);
        self::assertSame('m07', $second->next);

        $last = $segments->page($this->press, $second->next, 2);
        self::assertSame([], $last->members);
        self::assertNull($last->next);
    }

    public function testEndsAPageAtTheEndOfTheAudience(): void
    {
        $page = $this->segments(new MemoryAudience('m01', 'm02', 'm03'), new MemoryExclusions(), pageSize: 2)->page($this->press, null, 5);

        self::assertSame(['m01', 'm03'], $page->members);
        self::assertNull($page->next);
    }

    public function testBoundsTheMembersOneCallReads(): void
    {
        $segments = $this->segments(new MemoryAudience('m02', 'm05', 'm01'), new MemoryExclusions(), maxScanned: 2);

        $page = $segments->page($this->press, null, 10);
        self::assertSame(['m01'], $page->members);
        self::assertSame('m02', $page->next);

        $next = $segments->page($this->press, $page->next, 10);
        self::assertSame([], $next->members);
        self::assertNull($next->next);
    }

    public function testStopsReadingTheAudienceOnceThePageIsFull(): void
    {
        $audience = new MemoryAudience('m01', 'm02', 'm03', 'm04');
        $this->segments($audience, new MemoryExclusions(), pageSize: 1)->page($this->press, null, 1);

        self::assertSame(1, $audience->pages);
    }

    public function testFreezesTheMembersWithTheRuleAndTheDate(): void
    {
        $now = new DateTimeImmutable('2026-11-21 09:30');
        $segments = $this->segments(new MemoryAudience('m01', 'm02', 'm03', 'm04', 'm05'), new MemoryExclusions('m03'), pageSize: 2, clock: new FixedClock($now));

        $list = $segments->freeze($this->press);

        self::assertSame(['m01', 'm04'], $list->members);
        self::assertSame('press', $list->segment);
        self::assertSame('Press', $list->name);
        self::assertSame(new RuleWriter()->fingerprint($this->press->rule), $list->fingerprint);
        self::assertSame($now, $list->frozenAt);
    }

    public function testReadsTheAudienceToItsLastFullPage(): void
    {
        $audience = new MemoryAudience('m01', 'm03');
        $list = $this->segments($audience, new MemoryExclusions(), pageSize: 2)->freeze($this->press);

        self::assertSame(['m01', 'm03'], $list->members);
        self::assertSame(2, $audience->pages);
    }

    public function testBoundsAFullComputation(): void
    {
        $segments = $this->segments(new MemoryAudience('m01', 'm02', 'm03'), new MemoryExclusions(), maxMembers: 3);

        self::assertSame(['m01', 'm03'], $segments->freeze($this->press)->members);

        $this->expectException(SegmentTooLarge::class);
        $this->expectExceptionMessageIsOrContains('Computing the segment "press" reads more than 2 members.');

        $this->segments(new MemoryAudience('m01', 'm02', 'm03'), new MemoryExclusions(), maxMembers: 2)->freeze($this->press);
    }

    /**
     * @return iterable<string, array{list<list<string>>, string}>
     */
    public static function brokenAudiences(): iterable
    {
        yield 'out of order' => [[['m02', 'm01']], 'Audience gave "m01" out of ascending byte order, or at or before its cursor.'];
        yield 'twice the same' => [[['m01', 'm01']], 'Audience gave "m01" out of ascending byte order'];
        yield 'back to the cursor' => [[['m01', 'm02'], ['m02']], 'Audience gave "m02" out of ascending byte order'];
        yield 'an address' => [[['ana@example.com x']], 'Audience gave "ana@example.com x" out of ascending byte order'];
        yield 'too many' => [[['m01', 'm02', 'm03']], 'Audience gave 3 members when 2 were asked.'];
    }

    /**
     * @param list<list<string>> $pages
     */
    #[DataProvider('brokenAudiences')]
    public function testStopsOnAnAudienceThatBreaksItsContract(array $pages, string $message): void
    {
        $audience = new class($pages) implements Audience {
            /**
             * @param list<list<string>> $pages
             */
            public function __construct(private array $pages)
            {
            }

            public function members(?string $after, int $limit): array
            {
                return array_shift($this->pages) ?? [];
            }
        };

        $this->expectException(PortContractViolated::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->segments($audience, new MemoryExclusions(), pageSize: 2)->freeze($this->press);
    }

    /**
     * @return iterable<string, array{array<string, int>, string}>
     */
    public static function badLimits(): iterable
    {
        yield 'no page' => [['pageSize' => 0], 'pageSize is 0; it must lie between 1 and 1000.'];
        yield 'too large a page' => [['pageSize' => 1001], 'pageSize is 1001'];
        yield 'no scan' => [['maxScanned' => 0], 'maxScanned is 0; it must lie between 1 and 1000000.'];
        yield 'too large a scan' => [['maxScanned' => 1000001], 'maxScanned is 1000001'];
        yield 'no members' => [['maxMembers' => 0], 'maxMembers is 0; it must lie between 1 and 10000000.'];
        yield 'too many members' => [['maxMembers' => 10000001], 'maxMembers is 10000001'];
    }

    /**
     * @param array<string, int> $limits
     */
    #[DataProvider('badLimits')]
    public function testRefusesALimitOutOfRange(array $limits, string $message): void
    {
        $this->expectException(InvalidLimit::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Segments(new MemoryAudience(), new MemoryExclusions(), new FixedClock(), ...$limits);
    }

    public function testKeepsItsDefaultBounds(): void
    {
        $segments = new Segments(new MemoryAudience(), new MemoryExclusions(), new FixedClock());

        foreach (['pageSize' => 500, 'maxScanned' => 10000, 'maxMembers' => 200000] as $name => $default) {
            self::assertSame($default, new ReflectionProperty(Segments::class, $name)->getValue($segments), $name);
        }
    }

    public function testReadsAPageOfAHundredByDefault(): void
    {
        $facts = new MemoryFacts();
        $ids = array_map(static fn (int $n): string => \sprintf('m%03d', $n), range(1, 150));
        $facts->tags = array_fill_keys($ids, ['press']);

        $segments = new Segments(new MemoryAudience(...$ids), new MemoryExclusions(), new FixedClock());
        $page = $segments->page(new Segment('press', 'Press', new Rule(new Leaf('person.tagged', ['tags' => ['press']]), AudienceConditions::types(tags: $facts))));

        self::assertCount(100, $page->members);
        self::assertSame('m100', $page->next);
    }

    public function testWalksTheAudienceInOrderWithWhoBelongs(): void
    {
        $walked = iterator_to_array($this->segments(new MemoryAudience('m02', 'm01', 'm03'), new MemoryExclusions())->walk($this->press));

        self::assertSame(['m01' => true, 'm02' => false, 'm03' => true], $walked);
    }

    public function testAcceptsTheWidestLimits(): void
    {
        $segments = new Segments(new MemoryAudience('m01'), new MemoryExclusions(), new FixedClock(), 1000, 1000000, 10000000);
        $narrow = new Segments(new MemoryAudience('m01'), new MemoryExclusions(), new FixedClock(), 1, 1, 1);

        self::assertSame(['m01'], $segments->page($this->press, null, 1000)->members);
        self::assertSame(['m01'], $narrow->page($this->press, null, 1)->members);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function badPageLimits(): iterable
    {
        yield 'none' => [0];
        yield 'too many' => [1001];
    }

    #[DataProvider('badPageLimits')]
    public function testRefusesAPageLimitOutOfRange(int $limit): void
    {
        $this->expectException(InvalidLimit::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('limit is %d; it must lie between 1 and 1000.', $limit));

        $this->segments(new MemoryAudience(), new MemoryExclusions())->page($this->press, null, $limit);
    }

    private function segments(Audience $audience, MemoryExclusions $exclusions, int $pageSize = 500, int $maxScanned = 10000, int $maxMembers = 200000, ?FixedClock $clock = null): Segments
    {
        return new Segments($audience, $exclusions, $clock ?? new FixedClock(), $pageSize, $maxScanned, $maxMembers);
    }
}
