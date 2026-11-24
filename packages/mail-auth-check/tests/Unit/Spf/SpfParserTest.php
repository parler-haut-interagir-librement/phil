<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Tests\Unit\Spf;

use Phil\MailAuthCheck\Exception\InvalidRecord;
use Phil\MailAuthCheck\Spf\Qualifier;
use Phil\MailAuthCheck\Spf\SpfMechanism;
use Phil\MailAuthCheck\Spf\SpfParser;
use Phil\MailAuthCheck\Spf\SpfRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpfParser::class)]
#[CoversClass(SpfRecord::class)]
#[CoversClass(SpfMechanism::class)]
#[CoversClass(Qualifier::class)]
final class SpfParserTest extends TestCase
{
    public function testAnSpfRecordIsToldFromOtherTxtRecords(): void
    {
        self::assertTrue(SpfParser::isSpf('v=spf1'));
        self::assertTrue(SpfParser::isSpf('V=SPF1 -all'));
        self::assertFalse(SpfParser::isSpf('v=spf10 -all'));
        self::assertFalse(SpfParser::isSpf(' v=spf1 -all'));
        self::assertFalse(SpfParser::isSpf("v=spf1\n"));
        self::assertFalse(SpfParser::isSpf('google-site-verification=abc'));
    }

    public function testMechanismsAndModifiersAreReadInOrder(): void
    {
        $record = new SpfParser()->parse('v=spf1  ip4:192.0.2.0/24 ip6:2001:db8::/32 a mx:Mail.Example.org/24//64 ptr ptr:example.org exists:%{i}.x.example include:_spf.Provider.example ~all redirect=other.example exp=Explain.example unknown=1');

        self::assertSame('other.example', $record->redirect);
        self::assertSame('explain.example', $record->explanation);
        self::assertSame(
            [
                ['+', 'ip4', null, ':192.0.2.0/24'],
                ['+', 'ip6', null, ':2001:db8::/32'],
                ['+', 'a', null, ''],
                ['+', 'mx', 'mail.example.org', ':Mail.Example.org/24//64'],
                ['+', 'ptr', null, ''],
                ['+', 'ptr', 'example.org', ':example.org'],
                ['+', 'exists', '%{i}.x.example', ':%{i}.x.example'],
                ['+', 'include', '_spf.provider.example', ':_spf.Provider.example'],
                ['~', 'all', null, ''],
            ],
            array_map(static fn (SpfMechanism $m): array => [$m->qualifier->value, $m->name, $m->target, $m->value], $record->mechanisms),
        );
    }

    public function testQualifiersAndCaseAreRead(): void
    {
        $record = new SpfParser()->parse('v=spf1 -A ?MX +include:a.example -all');

        self::assertSame(
            [[Qualifier::Fail, 'a'], [Qualifier::Neutral, 'mx'], [Qualifier::Pass, 'include'], [Qualifier::Fail, 'all']],
            array_map(static fn (SpfMechanism $m): array => [$m->qualifier, $m->name], $record->mechanisms),
        );
    }

    public function testAnEmptyRecordHasNoMechanism(): void
    {
        $record = new SpfParser()->parse('v=spf1');

        self::assertSame([], $record->mechanisms);
        self::assertNull($record->redirect);
        self::assertNull($record->explanation);
        self::assertNull($record->all());
    }

    public function testTheFirstAllIsTheOneThatCounts(): void
    {
        $record = new SpfParser()->parse('v=spf1 a ~all -all');

        self::assertSame(Qualifier::SoftFail, $record->all()?->qualifier);
    }

    public function testTheMechanismsThatCostALookup(): void
    {
        $record = new SpfParser()->parse('v=spf1 include:a.example a mx ptr exists:b.example ip4:192.0.2.1 ip6:::1 all');

        self::assertSame(
            [true, true, true, true, true, false, false, false],
            array_map(static fn (SpfMechanism $m): bool => $m->costsLookup(), $record->mechanisms),
        );
    }

    public function testPrefixLengthsAtTheirLimitsAreRead(): void
    {
        $record = new SpfParser()->parse('v=spf1 a/32 mx//128 ip4:192.0.2.1/32 ip6:::1/128 a:x.example/0');

        self::assertCount(5, $record->mechanisms);
        self::assertSame('x.example', $record->mechanisms[4]->target);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidRecords(): iterable
    {
        yield 'not spf' => ['v=spf2 -all', 'the record does not start with "v=spf1".'];
        yield 'too long' => ['v=spf1 '.str_repeat('a', 4090), 'the record is longer than 4096 bytes.'];
        yield 'too many terms' => ['v=spf1'.str_repeat(' a', 65), 'the record has more than 64 terms.'];
        yield 'unknown mechanism' => ['v=spf1 foo -all', '"foo" is not an SPF mechanism.'];
        yield 'all with a value' => ['v=spf1 all:x.example', 'the mechanism "all" takes no value.'];
        yield 'include without domain' => ['v=spf1 include', 'the mechanism "include" needs a domain, as in "include:example.org".'];
        yield 'exists without colon' => ['v=spf1 exists/24', 'the mechanism "exists" needs a domain'];
        yield 'include with an empty domain' => ['v=spf1 include:', '"include:" does not name a valid domain.'];
        yield 'include of a bad domain' => ['v=spf1 include:a..b', '"include:a..b" does not name a valid domain.'];
        yield 'redirect twice' => ['v=spf1 redirect=a.example redirect=b.example', 'the modifier "redirect" is given twice.'];
        yield 'exp twice' => ['v=spf1 exp=a.example EXP=b.example', 'the modifier "exp" is given twice.'];
        yield 'empty redirect' => ['v=spf1 redirect=', '"redirect=" does not name a valid domain.'];
        yield 'a with a bad prefix' => ['v=spf1 a/33', 'the term "a/33" cannot be read.'];
        yield 'mx with a bad ip6 prefix' => ['v=spf1 mx//129', 'the term "mx//129" cannot be read.'];
        yield 'mx with junk' => ['v=spf1 mxfoo', '"mxfoo" is not an SPF mechanism.'];
        yield 'a with a bad separator' => ['v=spf1 a;x.example', 'the term "a;x.example" cannot be read.'];
        yield 'ptr with junk' => ['v=spf1 ptr/24', 'the term "ptr/24" cannot be read.'];
        yield 'ip4 without address' => ['v=spf1 ip4', 'the term "ip4" cannot be read.'];
        yield 'ip4 with a bad address' => ['v=spf1 ip4:300.0.0.1', 'the term "ip4:300.0.0.1" cannot be read.'];
        yield 'ip4 with an ip6 address' => ['v=spf1 ip4:::1', 'cannot be read.'];
        yield 'ip4 with a bad prefix' => ['v=spf1 ip4:192.0.2.0/33', 'cannot be read.'];
        yield 'ip6 with a bad prefix' => ['v=spf1 ip6:::1/129', 'cannot be read.'];
        yield 'ip6 with an ip4 address' => ['v=spf1 ip6:192.0.2.1', 'cannot be read.'];
        yield 'a term that is neither' => ['v=spf1 +', 'the term "+" is neither a mechanism nor a modifier.'];
        yield 'a macro with a space-like byte' => ["v=spf1 include:%{i}\x7F.example", 'cannot be read.'];
        yield 'a control character shown' => ["v=spf1 \x01x", 'the term "?x" is neither'];
        yield 'a modifier with a qualifier' => ['v=spf1 -x=y', '"x" is not an SPF mechanism.'];
        yield 'a modifier that ends with a line break' => ["v=spf1 redirect=a.example\n", 'the term "redirect=a.example?" is neither a mechanism nor a modifier.'];
        yield 'a modifier with a line break inside' => ["v=spf1 redirect=a.example\nb", 'the term "redirect=a.example?b" is neither a mechanism nor a modifier.'];
        yield 'a mechanism that ends with a line break' => ["v=spf1 a:x.example\n", 'the term "a:x.example?" is neither a mechanism nor a modifier.'];
        yield 'a mechanism with a line break inside' => ["v=spf1 a:x.example\nb", 'the term "a:x.example?b" is neither a mechanism nor a modifier.'];
        yield 'ip4 with junk after the prefix' => ['v=spf1 ip4:192.0.2.1/24x', 'the term "ip4:192.0.2.1/24x" cannot be read.'];
    }

    #[DataProvider('invalidRecords')]
    public function testAnInvalidRecordIsRefused(string $text, string $message): void
    {
        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains($message);

        new SpfParser()->parse($text);
    }

    public function testA4096ByteRecordAnd64TermsAreRead(): void
    {
        $record = new SpfParser()->parse('v=spf1'.str_repeat(' a', 63).' include:'.str_repeat('b', 60).'.example');
        self::assertCount(64, $record->mechanisms);

        $long = 'v=spf1 exp='.str_repeat('a.', 2041).'bcd';
        self::assertSame(4096, \strlen($long));

        // The domain is too long, but the length of the record itself was accepted.
        $this->expectExceptionMessageIsOrContains('does not name a valid domain');

        new SpfParser()->parse($long);
    }

    public function testALongMacroIsRefusedAndOneOf253Read(): void
    {
        $record = new SpfParser()->parse('v=spf1 exists:%'.str_repeat('a', 252));
        self::assertSame(253, \strlen((string) $record->mechanisms[0]->target));

        $this->expectException(InvalidRecord::class);

        new SpfParser()->parse('v=spf1 exists:%'.str_repeat('a', 253));
    }

    public function testATermShownInAMessageIsCutAt80Bytes(): void
    {
        try {
            new SpfParser()->parse('v=spf1 '.str_repeat('z', 100));
            self::fail('The record was read.');
        } catch (InvalidRecord $invalidRecord) {
            self::assertSame('"'.str_repeat('z', 80).'" is not an SPF mechanism.', $invalidRecord->getMessage());
        }
    }

    public function testAMacroIsKeptInLowercase(): void
    {
        $record = new SpfParser()->parse('v=spf1 exists:%{i}.X.Example redirect=%{D}.Other.Example');

        self::assertSame('%{i}.x.example', $record->mechanisms[0]->target);
        self::assertSame('%{d}.other.example', $record->redirect);
    }
}
