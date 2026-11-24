<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Tests\Unit\Check;

use Phil\MailAuthCheck\Check\ReturnPathCheck;
use Phil\MailAuthCheck\Check\SpfCheck;
use Phil\MailAuthCheck\Dns\StaticTxtResolver;
use Phil\MailAuthCheck\Finding;
use Phil\MailAuthCheck\Internal\SpfFindings;
use Phil\MailAuthCheck\SenderProfile;
use Phil\MailAuthCheck\Status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpfCheck::class)]
#[CoversClass(ReturnPathCheck::class)]
#[CoversClass(SpfFindings::class)]
#[CoversClass(Finding::class)]
final class SpfCheckTest extends TestCase
{
    public function testACompliantRecordPasses(): void
    {
        $findings = $this->spf(['example.org' => ['v=spf1 include:spf.provider.example -all'], 'spf.provider.example' => ['v=spf1 ip4:192.0.2.0/24 ~all']], ['spf.provider.example']);

        self::assertSame([
            ['spf', 'pass', 'spf.lookups', 'The SPF record of example.org needs 1 of the 10 DNS lookups allowed.'],
            ['spf', 'pass', 'spf.include', 'The SPF record of example.org includes spf.provider.example.'],
            ['spf', 'pass', 'spf.all-fail', 'The SPF record of example.org ends with "-all": mail from other servers fails SPF.'],
        ], $findings);
    }

    /**
     * @return iterable<string, array{array<string, list<string>>, list<string>, list<array{string, string, string}>}>
     */
    public static function records(): iterable
    {
        yield 'missing' => [[], [], [['fail', 'spf.missing', 'example.org publishes no SPF record (a TXT record starting with "v=spf1").']]];
        yield 'multiple' => [['example.org' => ['v=spf1 -all', 'v=spf1 ~all']], [], [['fail', 'spf.multiple', 'example.org publishes more than one SPF record, which receivers treat as a permanent error: merge them into one.']]];
        yield 'invalid' => [['example.org' => ['v=spf1 foo']], [], [['fail', 'spf.invalid', 'The SPF record of example.org cannot be read: its record cannot be read: "foo" is not an SPF mechanism.']]];
        yield 'soft fail' => [['example.org' => ['v=spf1 ~all']], [], [
            ['pass', 'spf.lookups', 'The SPF record of example.org needs 0 of the 10 DNS lookups allowed.'],
            ['pass', 'spf.all-softfail', 'The SPF record of example.org ends with "~all": mail from other servers soft-fails SPF, and DMARC decides.'],
        ]];
        yield 'plus all' => [['example.org' => ['v=spf1 +all']], [], [
            ['pass', 'spf.lookups', 'The SPF record of example.org needs 0 of the 10 DNS lookups allowed.'],
            ['fail', 'spf.all-pass', 'The SPF record of example.org ends with "+all", which lets any server send as it.'],
        ]];
        yield 'neutral' => [['example.org' => ['v=spf1 ?all']], [], [
            ['pass', 'spf.lookups', 'The SPF record of example.org needs 0 of the 10 DNS lookups allowed.'],
            ['warning', 'spf.all-neutral', 'The SPF record of example.org ends with "?all", which says nothing of other servers: prefer "~all" or "-all".'],
        ]];
        yield 'no all' => [['example.org' => ['v=spf1 mx']], [], [
            ['pass', 'spf.lookups', 'The SPF record of example.org needs 1 of the 10 DNS lookups allowed.'],
            ['warning', 'spf.no-all', 'The SPF record of example.org has no "all", so other servers get a neutral result: end it with "~all" or "-all".'],
        ]];
        yield 'eight lookups' => [['example.org' => ['v=spf1'.str_repeat(' mx', 8).' -all']], [], [
            ['pass', 'spf.lookups', 'The SPF record of example.org needs 8 of the 10 DNS lookups allowed.'],
            ['pass', 'spf.all-fail', 'The SPF record of example.org ends with "-all": mail from other servers fails SPF.'],
        ]];
        yield 'nine lookups' => [['example.org' => ['v=spf1'.str_repeat(' mx', 9).' -all']], [], [
            ['warning', 'spf.lookups-near-limit', 'The SPF record of example.org needs 9 of the 10 DNS lookups allowed. One more provider may break it.'],
            ['pass', 'spf.all-fail', 'The SPF record of example.org ends with "-all": mail from other servers fails SPF.'],
        ]];
        yield 'eleven lookups' => [['example.org' => ['v=spf1'.str_repeat(' mx', 11).' -all']], [], [
            ['fail', 'spf.too-many-lookups', 'The SPF record of example.org needs more than 10 DNS lookups (include, a, mx, ptr, exists, redirect), which receivers treat as a permanent error.'],
            ['pass', 'spf.all-fail', 'The SPF record of example.org ends with "-all": mail from other servers fails SPF.'],
        ]];
        yield 'eleven lookups without all' => [['example.org' => ['v=spf1'.str_repeat(' mx', 11)]], [], [
            ['fail', 'spf.too-many-lookups', 'The SPF record of example.org needs more than 10 DNS lookups (include, a, mx, ptr, exists, redirect), which receivers treat as a permanent error.'],
        ]];
        yield 'loop, broken, macro and a missing include' => [
            [
                'example.org' => ['v=spf1 include:a.example include:gone.example include:%{i}.x.example -all'],
                'a.example' => ['v=spf1 include:example.org ~all'],
            ],
            ['a.example', 'spf.provider.example'],
            [
                ['pass', 'spf.lookups', 'The SPF record of example.org needs 4 of the 10 DNS lookups allowed.'],
                ['fail', 'spf.include-loop', 'The SPF record of example.org includes example.org again inside itself, a loop receivers treat as a permanent error.'],
                ['fail', 'spf.include-broken', 'The SPF record of example.org includes gone.example, but it has no SPF record.'],
                ['warning', 'spf.macro', 'The SPF record of example.org names %{i}.x.example, a macro that depends on each message: it is counted, not followed.'],
                ['pass', 'spf.include', 'The SPF record of example.org includes a.example.'],
                ['fail', 'spf.include-missing', 'The SPF record of example.org does not include spf.provider.example: add "include:spf.provider.example".'],
                ['pass', 'spf.all-fail', 'The SPF record of example.org ends with "-all": mail from other servers fails SPF.'],
            ],
        ];
    }

    /**
     * @param array<string, list<string>>         $records
     * @param list<string>                        $includes
     * @param list<array{string, string, string}> $expected
     */
    #[DataProvider('records')]
    public function testEachStateGivesItsFindings(array $records, array $includes, array $expected): void
    {
        self::assertSame(
            array_map(static fn (array $finding): array => ['spf', ...$finding], $expected),
            $this->spf($records, $includes),
        );
    }

    public function testAFailedLookupFails(): void
    {
        $findings = new SpfCheck()->run(new SenderProfile('example.org'), new StaticTxtResolver([], ['example.org']));

        self::assertSame(Status::Fail, $findings[0]->status);
        self::assertSame('spf.lookup-failed', $findings[0]->code);
        self::assertSame('The SPF record of example.org could not be read: the DNS lookup failed.', $findings[0]->message);
    }

    public function testTheReturnPathOfTheSendingDomainIsTheSpfCheckItself(): void
    {
        $findings = new ReturnPathCheck()->run(new SenderProfile('example.org'), new StaticTxtResolver());

        self::assertEquals([Finding::pass('return-path', 'return-path.same-domain', 'The return path is the sending domain example.org: its SPF record is the one the SPF check reads.')], $findings);
    }

    public function testAnotherReturnPathIsCheckedForTheExpectedProviders(): void
    {
        $profile = new SenderProfile('example.org', 'bounce.example.org', spfIncludes: ['spf.provider.example']);
        $findings = new ReturnPathCheck()->run($profile, new StaticTxtResolver([
            'bounce.example.org' => ['v=spf1 -all'],
        ]));

        self::assertSame(
            [
                ['return-path', 'pass', 'return-path.lookups', 'The SPF record of bounce.example.org needs 0 of the 10 DNS lookups allowed.'],
                ['return-path', 'fail', 'return-path.include-missing', 'The SPF record of bounce.example.org does not include spf.provider.example: add "include:spf.provider.example".'],
                ['return-path', 'pass', 'return-path.all-fail', 'The SPF record of bounce.example.org ends with "-all": mail from other servers fails SPF.'],
            ],
            array_map(static fn (Finding $f): array => [$f->check, $f->status->value, $f->code, $f->message], $findings),
        );
    }

    /**
     * @param array<string, list<string>> $records
     * @param list<string>                $includes
     *
     * @return list<array{string, string, string, string}>
     */
    private function spf(array $records, array $includes): array
    {
        $findings = new SpfCheck()->run(new SenderProfile('example.org', spfIncludes: $includes), new StaticTxtResolver($records));

        return array_map(static fn (Finding $f): array => [$f->check, $f->status->value, $f->code, $f->message], $findings);
    }
}
