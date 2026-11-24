<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Tests\Unit\Spf;

use Phil\MailAuthCheck\Dns\StaticTxtResolver;
use Phil\MailAuthCheck\Internal\SpfWalk;
use Phil\MailAuthCheck\Spf\Qualifier;
use Phil\MailAuthCheck\Spf\SpfAnalyser;
use Phil\MailAuthCheck\Spf\SpfAnalysis;
use Phil\MailAuthCheck\Spf\SpfState;
use Phil\MailAuthCheck\Tests\Support\CountingResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpfAnalyser::class)]
#[CoversClass(SpfAnalysis::class)]
#[CoversClass(SpfWalk::class)]
#[CoversClass(SpfState::class)]
final class SpfAnalyserTest extends TestCase
{
    public function testNestedIncludesAreFollowedAndTheirLookupsCounted(): void
    {
        $analysis = $this->analyse([
            'example.org' => ['google-site-verification=x', 'v=spf1 include:a.example mx -all'],
            'a.example' => ['v=spf1 include:b.example ip4:192.0.2.1 ~all'],
            'b.example' => ['v=spf1 a ?all'],
        ]);

        self::assertSame(SpfState::Found, $analysis->state);
        self::assertNull($analysis->reason);
        self::assertSame('example.org', $analysis->domain);
        self::assertNotNull($analysis->record);
        self::assertSame(4, $analysis->lookups);
        self::assertSame(['a.example', 'b.example'], $analysis->includes);
        self::assertSame(Qualifier::Fail, $analysis->all);
        self::assertSame([], $analysis->loops);
        self::assertSame([], $analysis->broken);
        self::assertSame([], $analysis->macros);
        self::assertFalse($analysis->exceedsLookupLimit());
    }

    public function testALookupAfterAMechanismThatCostsNoneIsCounted(): void
    {
        $analysis = $this->analyse([
            'example.org' => ['v=spf1 ip4:192.0.2.1 include:a.example -all'],
            'a.example' => ['v=spf1 ip6:2001:db8::1 a -all'],
        ]);

        self::assertSame(2, $analysis->lookups);
        self::assertSame(['a.example'], $analysis->includes);
    }

    public function testTenLookupsAreWithinTheLimit(): void
    {
        $analysis = $this->analyse($this->includes(10));

        self::assertSame(10, $analysis->lookups);
        self::assertFalse($analysis->exceedsLookupLimit());
        self::assertCount(10, $analysis->includes);
    }

    public function testTheEleventhLookupPassesTheLimitAndEndsTheWalk(): void
    {
        $records = $this->includes(12);
        $resolver = new CountingResolver($records);
        $analysis = new SpfAnalyser()->analyse('example.org', $resolver);

        self::assertSame(11, $analysis->lookups);
        self::assertTrue($analysis->exceedsLookupLimit());
        self::assertCount(10, $analysis->includes);
        // The record itself and the ten includes followed: never the eleventh.
        self::assertCount(11, $resolver->asked);
        self::assertSame(Qualifier::Fail, $analysis->all);
    }

    public function testLookupsInsideIncludesCountTowardTheLimit(): void
    {
        $analysis = $this->analyse([
            'example.org' => ['v=spf1 include:a.example include:b.example -all'],
            'a.example' => ['v=spf1 a mx ptr exists:x.example a:y.example -all'],
            'b.example' => ['v=spf1 mx mx mx ~all'],
        ]);

        self::assertSame(10, $analysis->lookups);
        self::assertFalse($analysis->exceedsLookupLimit());
    }

    public function testARedirectPastTheLimitIsNotFollowed(): void
    {
        $records = $this->includes(10);
        $records['example.org'] = [str_replace(' -all', ' redirect=r.example', $records['example.org'][0])];
        $records['r.example'] = ['v=spf1 -all'];
        $resolver = new CountingResolver($records);
        $analysis = new SpfAnalyser()->analyse('example.org', $resolver);

        self::assertSame(11, $analysis->lookups);
        self::assertNull($analysis->all);
        self::assertNotContains('r.example', $resolver->asked);
    }

    public function testALoopIsReportedAndNotFollowedTwice(): void
    {
        $resolver = new CountingResolver([
            'example.org' => ['v=spf1 include:a.example -all'],
            'a.example' => ['v=spf1 include:example.org include:a.example ~all'],
        ]);
        $analysis = new SpfAnalyser()->analyse('example.org', $resolver);

        self::assertSame(['example.org', 'a.example'], $analysis->loops);
        self::assertSame(['a.example'], $analysis->includes);
        self::assertSame(3, $analysis->lookups);
        self::assertSame(['example.org', 'a.example'], $resolver->asked);
    }

    public function testADomainIncludedTwiceOnSeparateBranchesIsNotALoop(): void
    {
        $analysis = $this->analyse([
            'example.org' => ['v=spf1 include:a.example include:b.example -all'],
            'a.example' => ['v=spf1 include:c.example -all'],
            'b.example' => ['v=spf1 include:c.example -all'],
            'c.example' => ['v=spf1 ip4:192.0.2.1 -all'],
        ]);

        self::assertSame([], $analysis->loops);
        self::assertSame(['a.example', 'c.example', 'b.example'], $analysis->includes);
        self::assertSame(4, $analysis->lookups);
    }

    public function testBrokenIncludesSayWhy(): void
    {
        $analysis = new SpfAnalyser()->analyse('example.org', new StaticTxtResolver([
            'example.org' => ['v=spf1 include:none.example include:two.example include:bad.example include:down.example -all'],
            'two.example' => ['v=spf1 -all', 'v=spf1 ~all'],
            'bad.example' => ['v=spf1 foo'],
        ], ['down.example']));

        self::assertSame([
            'none.example' => 'it has no SPF record',
            'two.example' => 'it has more than one SPF record',
            'bad.example' => 'its record cannot be read: "foo" is not an SPF mechanism.',
            'down.example' => 'its DNS lookup failed',
        ], $analysis->broken);
        self::assertSame(4, $analysis->lookups);
    }

    public function testAMacroIsCountedNotFollowed(): void
    {
        $analysis = $this->analyse(['example.org' => ['v=spf1 include:%{d}.spf.example exists:%{i}.x.example -all']]);

        self::assertSame(['%{d}.spf.example'], $analysis->macros);
        self::assertSame([], $analysis->includes);
        self::assertSame(2, $analysis->lookups);
    }

    public function testARedirectGivesItsAllAndCountsALookup(): void
    {
        $analysis = $this->analyse([
            'example.org' => ['v=spf1 ip4:192.0.2.1 redirect=r.example'],
            'r.example' => ['v=spf1 include:p.example ~all'],
            'p.example' => ['v=spf1 -all'],
        ]);

        self::assertSame(2, $analysis->lookups);
        self::assertSame(['r.example', 'p.example'], $analysis->includes);
        self::assertSame(Qualifier::SoftFail, $analysis->all);
    }

    public function testARedirectIsIgnoredWhenTheRecordHasAnAll(): void
    {
        $resolver = new CountingResolver([
            'example.org' => ['v=spf1 +all redirect=r.example'],
            'r.example' => ['v=spf1 -all'],
        ]);
        $analysis = new SpfAnalyser()->analyse('example.org', $resolver);

        self::assertSame(0, $analysis->lookups);
        self::assertSame(Qualifier::Pass, $analysis->all);
        self::assertSame(['example.org'], $resolver->asked);
    }

    public function testARecordWithoutAllNorRedirectGivesNone(): void
    {
        $analysis = $this->analyse(['example.org' => ['v=spf1 mx']]);

        self::assertNull($analysis->all);
        self::assertSame(1, $analysis->lookups);
    }

    public function testARedirectToABrokenDomainGivesNone(): void
    {
        $analysis = $this->analyse(['example.org' => ['v=spf1 redirect=gone.example']]);

        self::assertNull($analysis->all);
        self::assertSame(['gone.example' => 'it has no SPF record'], $analysis->broken);
    }

    public function testTheStatesOfTheDomainItself(): void
    {
        $resolver = new StaticTxtResolver([
            'two.example' => ['v=spf1 -all', 'v=spf1 ~all'],
            'bad.example' => ['v=spf1 all:x'],
            'other.example' => ['hello'],
        ], ['down.example']);
        $analyser = new SpfAnalyser();

        $missing = $analyser->analyse('other.example', $resolver);
        self::assertSame([SpfState::Missing, 'it has no SPF record', null, 0], [$missing->state, $missing->reason, $missing->record, $missing->lookups]);
        self::assertSame(SpfState::Multiple, $analyser->analyse('two.example', $resolver)->state);
        self::assertSame(SpfState::LookupFailed, $analyser->analyse('down.example', $resolver)->state);

        $invalid = $analyser->analyse('bad.example', $resolver);
        self::assertSame(SpfState::Invalid, $invalid->state);
        self::assertSame('its record cannot be read: the mechanism "all" takes no value.', $invalid->reason);
    }

    /**
     * @return array<string, list<string>> a record of as many includes, each ending with `-all`
     */
    private function includes(int $count): array
    {
        $records = ['example.org' => ['v=spf1 '.implode(' ', array_map(static fn (int $i): string => 'include:i'.$i.'.example', range(1, $count))).' -all']];

        foreach (range(1, $count) as $i) {
            $records['i'.$i.'.example'] = ['v=spf1 ip4:192.0.2.'.$i.' -all'];
        }

        return $records;
    }

    /**
     * @param array<string, list<string>> $records
     */
    private function analyse(array $records): SpfAnalysis
    {
        return new SpfAnalyser()->analyse('example.org', new StaticTxtResolver($records));
    }
}
