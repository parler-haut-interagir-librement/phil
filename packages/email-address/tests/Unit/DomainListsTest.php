<?php

declare(strict_types=1);

namespace Phil\EmailAddress\Tests\Unit;

use Phil\EmailAddress\DisposableDomains;
use Phil\EmailAddress\DomainRules;
use Phil\EmailAddress\EmailAddress;
use Phil\EmailAddress\Exception\InvalidDomainList;
use Phil\EmailAddress\Internal\DomainSet;
use Phil\EmailAddress\Problem;
use Phil\EmailAddress\SyntaxError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DisposableDomains::class)]
#[CoversClass(DomainRules::class)]
#[CoversClass(DomainSet::class)]
#[CoversClass(InvalidDomainList::class)]
final class DomainListsTest extends TestCase
{
    public function testHoldsADomainAndItsSubdomains(): void
    {
        $disposable = DisposableDomains::of(['Mailinator.COM', 'bücher.example', 'mailinator.com']);

        self::assertSame(2, $disposable->count());
        self::assertTrue($disposable->contains('mailinator.com'));
        self::assertTrue($disposable->contains('a.b.mailinator.com'));
        self::assertTrue($disposable->contains(EmailAddress::parse('ada@BÜCHER.example')));
        self::assertTrue($disposable->contains('xn--bcher-kva.example'));
        self::assertFalse($disposable->contains('notmailinator.com'));
        self::assertFalse($disposable->contains('com'));
        self::assertFalse($disposable->contains('example.org'));
    }

    public function testMergesTwoLists(): void
    {
        $merged = DisposableDomains::of(['a.example'])->merge(DisposableDomains::of(['b.example', 'a.example', 'c.example']));

        self::assertSame(3, $merged->count());
        self::assertTrue($merged->contains('c.example'));
        self::assertTrue($merged->contains('a.example'));
        self::assertTrue($merged->contains('b.example'));
    }

    public function testReadsTheBundledList(): void
    {
        $bundled = DisposableDomains::bundled();
        /** @var list<string> $data */
        $data = require \dirname(__DIR__, 2).'/data/disposable-domains.php';

        self::assertSame(\count($data), $bundled->count());

        foreach (\array_slice($data, 0, 20) as $domain) {
            self::assertTrue($bundled->contains($domain));
        }
    }

    public function testRefusesAnEntryThatIsNotADomain(): void
    {
        $this->expectExceptionObject(new InvalidDomainList('"ex ample.com" is not a domain: the domain is not a valid domain name.'));

        DisposableDomains::of(['example.org', 'ex ample.com']);
    }

    public function testRefusesADeniedDomainAndItsSubdomains(): void
    {
        $rules = DomainRules::of(denied: ['example.com']);

        self::assertSame(Problem::DeniedDomain, $rules->check(EmailAddress::parse('ada@example.com')));
        self::assertSame(Problem::DeniedDomain, $rules->check(EmailAddress::parse('ada@mail.example.com')));
        self::assertNull($rules->check(EmailAddress::parse('ada@example.org')));
    }

    public function testAcceptsOnlyTheAllowedDomainsWhenSomeAreNamed(): void
    {
        $rules = DomainRules::of(allowedOnly: ['example.com'])->withAllowedOnly(['example.net']);

        self::assertNull($rules->check(EmailAddress::parse('ada@example.com')));
        self::assertNull($rules->check(EmailAddress::parse('ada@staff.example.net')));
        self::assertSame(Problem::DomainNotAllowed, $rules->check(EmailAddress::parse('ada@example.org')));
    }

    public function testRefusesADeniedDomainEvenWhenAllowed(): void
    {
        $rules = DomainRules::of(allowedOnly: ['example.com'])->withDenied(['old.example.com'])->withDenied(['x.example.com']);

        self::assertNull($rules->check(EmailAddress::parse('ada@example.com')));
        self::assertSame(Problem::DeniedDomain, $rules->check(EmailAddress::parse('ada@old.example.com')));
        self::assertSame(Problem::DeniedDomain, $rules->check(EmailAddress::parse('ada@x.example.com')));
    }

    public function testAcceptsEveryDomainWithoutRules(): void
    {
        self::assertNull(DomainRules::of()->check(EmailAddress::parse('ada@example.com')));
    }

    public function testRefusesARuleThatIsNotADomain(): void
    {
        $this->expectException(InvalidDomainList::class);

        DomainRules::of(allowedOnly: ['localhost']);
    }

    public function testDescribesTheErrorsOfAList(): void
    {
        self::assertSame(
            'Line 3 of the domain list is not a domain: the domain has no dot.',
            InvalidDomainList::entry(3, SyntaxError::SingleLabelDomain)->getMessage(),
        );
        self::assertSame('The file "/x" cannot be written.', InvalidDomainList::unwritable('/x')->getMessage());
    }
}
