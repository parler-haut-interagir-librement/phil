<?php

declare(strict_types=1);

namespace Phil\EmailAddress\Tests\Unit;

use Phil\EmailAddress\EmailAddress;
use Phil\EmailAddress\Exception\InvalidDomainList;
use Phil\EmailAddress\Internal\TopLevelLabel;
use Phil\EmailAddress\TopLevelDomains;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TopLevelDomains::class)]
#[CoversClass(TopLevelLabel::class)]
final class TopLevelDomainsTest extends TestCase
{
    public function testHoldsTheLastLabelOfADomain(): void
    {
        $domains = TopLevelDomains::of(['COM', 'ca', 'xn--p1ai', 'com']);

        self::assertSame(3, $domains->count());
        self::assertFalse($domains->isEmpty());
        self::assertTrue($domains->contains('example.com'));
        self::assertTrue($domains->contains('a.b.example.CA'));
        self::assertTrue($domains->contains('com'));
        self::assertTrue($domains->contains(EmailAddress::parse('ada@пример.рф')));
        self::assertFalse($domains->contains('example.co'));
        self::assertFalse($domains->contains('example.com.invalid'));
        self::assertFalse($domains->contains('[192.0.2.1]'));
        self::assertFalse($domains->contains(''));
    }

    public function testRefusesALabelThatCannotBeATopLevelDomain(): void
    {
        $this->expectExceptionObject(new InvalidDomainList('"co.uk" is not a domain: the top-level domain is numeric or a single letter.'));

        TopLevelDomains::of(['com', 'co.uk']);
    }

    public function testReadsTheBundledList(): void
    {
        $bundled = TopLevelDomains::bundled();
        /** @var list<string> $data */
        $data = require \dirname(__DIR__, 2).'/data/top-level-domains.php';

        self::assertSame(\count($data), $bundled->count());
        self::assertSame([] === $data, $bundled->isEmpty());

        foreach (\array_slice($data, 0, 20) as $label) {
            self::assertTrue($bundled->contains('example.'.$label));
        }
    }

    public function testAnEmptyListHoldsNothing(): void
    {
        $empty = TopLevelDomains::of([]);

        self::assertTrue($empty->isEmpty());
        self::assertSame(0, $empty->count());
        self::assertFalse($empty->contains('example.com'));
    }
}
