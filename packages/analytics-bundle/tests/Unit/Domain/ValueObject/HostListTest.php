<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\ValueObject;

use Phil\AnalyticsBundle\Domain\ValueObject\HostList;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(HostList::class)]
final class HostListTest extends TestCase
{
    public function testAnEntryMatchesItselfAndItsSubdomains(): void
    {
        $list = HostList::of(['example.com']);

        self::assertTrue($list->matches('example.com'));
        self::assertTrue($list->matches('www.example.com'));
        self::assertTrue($list->matches('WWW.Example.com.'));
    }

    /**
     * A suffix match without a label boundary would make every domain ending in the same letters a match.
     */
    public function testAnEntryDoesNotMatchAnotherDomainEndingTheSameWay(): void
    {
        $list = HostList::of(['example.com']);

        self::assertFalse($list->matches('badexample.com'));
        self::assertFalse($list->matches('example.com.evil.test'));
        self::assertFalse($list->matches(''));
    }

    public function testAWildcardLabelMatchesAPublicSuffix(): void
    {
        $list = HostList::of(['google.{}']);

        self::assertTrue($list->matches('google.com'));
        self::assertTrue($list->matches('google.fr'));
        self::assertTrue($list->matches('www.google.co.uk'));
        self::assertTrue($list->matches('google.com.au'));
        self::assertFalse($list->matches('google'));
        self::assertFalse($list->matches('notgoogle.com'));
    }

    /**
     * Anyone can register `attacker.example`: the wildcard must not take a registrable domain for a suffix.
     */
    public function testAWildcardLabelDoesNotMatchARegistrableDomain(): void
    {
        $list = HostList::of(['google.{}']);

        self::assertFalse($list->matches('google.attacker.example'));
        self::assertFalse($list->matches('www.google.evil.co.uk'));
        self::assertFalse($list->matches('google.com.attacker'));
    }

    public function testEntriesAreNormalizedAndDeduplicated(): void
    {
        self::assertSame(['example.com'], HostList::of([' Example.COM', 'example.com'])->entries);
    }

    public function testADuplicateDoesNotHideTheEntriesAfterIt(): void
    {
        $list = HostList::of(['a.example', 'a.example', 'b.example']);

        self::assertSame(['a.example', 'b.example'], $list->entries);
        self::assertTrue($list->matches('b.example'));
    }

    public function testAHostWithATrailingNewlineMatchesNothing(): void
    {
        self::assertFalse(HostList::of(['example.com'])->matches("example.com\n"));
        self::assertFalse(HostList::of(['google.{}'])->matches("google.com\n"));
    }

    public function testAnEmptyHostMatchesNothing(): void
    {
        self::assertFalse(HostList::of(['{}'])->matches('.'));
        self::assertFalse(HostList::of(['{}'])->matches(''));
    }

    /**
     * Literal entries and `{}` entries side by side, in a list as long as a referrer-spam list.
     */
    public function testALongMixedListMatchesEachKindOfEntry(): void
    {
        $entries = array_map(static fn (int $index): string => \sprintf('spam-%d.example', $index), range(1, 5000));
        $list = HostList::of([...$entries, 'bing.{}', 'duckduckgo.com', 'yandex.{}']);

        self::assertTrue($list->matches('spam-4321.example'));
        self::assertTrue($list->matches('a.b.spam-1.example'));
        self::assertFalse($list->matches('spam-5001.example'));
        self::assertFalse($list->matches('xspam-1.example'));
        self::assertTrue($list->matches('www.bing.com'));
        self::assertTrue($list->matches('yandex.com.tr'));
        self::assertFalse($list->matches('bing.attacker.example'));
        self::assertTrue($list->matches('duckduckgo.com'));
        self::assertFalse($list->matches('duckduckgo.co'));
        self::assertFalse($list->matches('example'));
        self::assertTrue($list->matches('.spam-2.example'));
    }

    /**
     * A list of templated entries far longer than one pattern could hold: every entry still matches, and nothing else.
     */
    public function testALongTemplatedListMatchesEveryEntry(): void
    {
        $entries = array_map(static fn (int $index): string => \sprintf('spam-%d.{}', $index), range(1, 2000));
        $list = HostList::of([...$entries, 'www.{}.blog.{}']);

        self::assertTrue($list->matches('spam-1.com'));
        self::assertTrue($list->matches('a.spam-1234.co.uk'));
        self::assertTrue($list->matches('spam-2000.fr'));
        self::assertTrue($list->matches('www.x.blog.com.au'));
        self::assertFalse($list->matches('spam-2001.com'));
        self::assertFalse($list->matches('spam-1.attacker.example'));
        self::assertFalse($list->matches('x.blog.com'));
        self::assertFalse($list->matches('example.com'));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function templatedHosts(): iterable
    {
        yield 'a wildcard over one label' => ['{}.example', 'a-1.example', true];
        yield 'a wildcard over a generic second level' => ['www.{}', 'www.gov.uk', true];
        yield 'a wildcard over a label that is not a host name' => ['{}.example', 'a_b.example', false];
        yield 'a wildcard over an empty label' => ['{}.example', '.example', false];
        yield 'a wildcard spelled in the host' => ['google.{}', 'google.{}', false];
        yield 'a country code of three characters' => ['google.{}', 'google.co.uk1', false];
        yield 'a country code with a digit' => ['google.{}', 'google.co.u1', false];
        yield 'a country code of one letter' => ['google.{}', 'google.co.u', false];
        yield 'a second level that is not generic' => ['google.{}', 'google.xy.uk', false];
        yield 'a wildcard alone on a country code' => ['{}', 'uk', true];
        yield 'two wildcards on two pairs' => ['{}.{}', 'co.uk.com.au', true];
        yield 'two wildcards on three labels' => ['x.{}.{}', 'x.co.uk.fr', true];
        yield 'an entry longer than the host' => ['a.b.{}', 'b.com', false];
        yield 'a wildcard inside the entry' => ['a.{}.b', 'a.co.uk.b', true];
        yield 'a literal after a wildcard that does not follow' => ['a.{}.b', 'c.co.uk.b', false];
    }

    #[DataProvider('templatedHosts')]
    public function testATemplatedEntryMatchesWholeLabels(string $entry, string $host, bool $matches): void
    {
        self::assertSame($matches, HostList::of([$entry])->matches($host));
    }

    /**
     * Two templated entries that share their right-hand labels each keep their own end.
     */
    public function testTemplatedEntriesSharingTheirSuffixKeepTheirOwnEnds(): void
    {
        $list = HostList::of(['a.{}', 'b.a.{}', 'c.{}.{}']);

        self::assertTrue($list->matches('a.com'));
        self::assertTrue($list->matches('b.a.com'));
        self::assertTrue($list->matches('c.co.uk.fr'));
        self::assertFalse($list->matches('c.fr'));
        self::assertFalse($list->matches('b.com'));
    }

    /**
     * Each way a run of generic pairs can be split is the same walk from the same place: a host made only of pairs,
     * against an entry that never matches, ends at once instead of trying every split.
     */
    public function testAHostOfManyGenericPairsIsWalkedOnce(): void
    {
        $list = HostList::of([implode('.', ['missing', ...array_fill(0, 80, '{}')])]);
        $host = implode('.', array_fill(0, 40, 'co.uk'));

        $started = hrtime(true);

        self::assertFalse($list->matches($host));
        self::assertLessThan(1_000_000_000, hrtime(true) - $started);
    }

    public function testAnEmptyListMatchesNothing(): void
    {
        self::assertFalse(HostList::empty()->matches('example.com'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notAHost(): iterable
    {
        yield 'empty' => [''];
        yield 'a URL' => ['https://example.com'];
        yield 'a path' => ['example.com/search'];
        yield 'an empty label' => ['example..com'];
        yield 'a space' => ['exa mple.com'];
    }

    #[DataProvider('notAHost')]
    public function testAnEntryThatIsNotAHostIsRefused(string $entry): void
    {
        $this->expectException(InvalidValue::class);

        HostList::of([$entry]);
    }
}
