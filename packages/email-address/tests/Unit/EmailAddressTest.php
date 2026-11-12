<?php

declare(strict_types=1);

namespace Phil\EmailAddress\Tests\Unit;

use Phil\EmailAddress\EmailAddress;
use Phil\EmailAddress\Exception\InvalidEmailAddress;
use Phil\EmailAddress\Internal\DomainName;
use Phil\EmailAddress\SyntaxError;
use Phil\EmailAddress\SyntaxPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailAddress::class)]
#[CoversClass(DomainName::class)]
#[CoversClass(SyntaxPolicy::class)]
#[CoversClass(SyntaxError::class)]
#[CoversClass(InvalidEmailAddress::class)]
final class EmailAddressTest extends TestCase
{
    public function testKeepsThePartBeforeTheAtAsWrittenAndTheDomainInLowercase(): void
    {
        $address = EmailAddress::parse(" \tAda.Lovelace+news@Example.COM\r\n");

        self::assertSame('Ada.Lovelace+news', $address->localPart);
        self::assertSame('example.com', $address->domain);
        self::assertSame('example.com', $address->asciiDomain);
        self::assertSame('Ada.Lovelace+news@example.com', $address->toString());
        self::assertSame('Ada.Lovelace+news@example.com', (string) $address);
        self::assertSame('Ada.Lovelace+news@example.com', $address->toAscii());
        self::assertFalse($address->requiresSmtpUtf8());
        self::assertFalse($address->isIpLiteral());
    }

    public function testKeepsAnInternationalDomainInItsScriptAndInAscii(): void
    {
        $address = EmailAddress::parse('ada@BÜCHER.example');

        self::assertSame('bücher.example', $address->domain);
        self::assertSame('xn--bcher-kva.example', $address->asciiDomain);
        self::assertSame('ada@bücher.example', $address->toString());
        self::assertSame('ada@xn--bcher-kva.example', $address->toAscii());
        self::assertTrue($address->equals(EmailAddress::parse('ada@xn--bcher-kva.example')));
    }

    public function testComparesThePartBeforeTheAtWithItsCase(): void
    {
        $address = EmailAddress::parse('ada@example.com');

        self::assertTrue($address->equals(EmailAddress::parse('ada@EXAMPLE.com')));
        self::assertFalse($address->equals(EmailAddress::parse('Ada@example.com')));
        self::assertFalse($address->equals(EmailAddress::parse('ada@example.org')));
    }

    public function testAcceptsEveryCharacterOfAnAtom(): void
    {
        $local = "a1!#$%&'*+/=?^_`{|}~-.Z9";

        self::assertSame($local, EmailAddress::parse($local.'@example.com')->localPart);
    }

    public function testAcceptsTheLongestAddressInAscii(): void
    {
        $address = EmailAddress::parse(str_repeat('a', 64).'@'.str_repeat('é.', 20).str_repeat('a', 25).'.com');

        self::assertSame(254, \strlen($address->toAscii()));
    }

    public function testAcceptsTheLongestAddress(): void
    {
        $local = str_repeat('a', 64);
        $domain = str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 57).'.com';

        self::assertSame(254, \strlen($local.'@'.$domain));
        self::assertSame($local.'@'.$domain, EmailAddress::parse($local.'@'.$domain)->toString());
    }

    /**
     * @return iterable<string, array{string, SyntaxError}>
     */
    public static function invalid(): iterable
    {
        yield 'empty' => ['', SyntaxError::Empty];
        yield 'blank' => [" \t\r\n", SyntaxError::Empty];
        yield 'too long as written' => [str_repeat('a', 64).'@'.str_repeat('b', 186).'.com', SyntaxError::TooLong];
        yield 'too long in ASCII' => [str_repeat('a', 64).'@'.str_repeat('é.', 30).'com', SyntaxError::TooLong];
        yield 'domain too long in ASCII' => ['ada@'.str_repeat('é.', 40).'com', SyntaxError::DomainTooLong];
        yield 'label too long in ASCII' => ['ada@'.str_repeat('ü', 60).'.com', SyntaxError::LabelTooLong];
        yield 'one octet too long in ASCII' => [str_repeat('a', 64).'@'.str_repeat('é.', 20).str_repeat('a', 26).'.com', SyntaxError::TooLong];
        yield 'decomposed letters counted composed only when allowed' => [str_repeat("e\u{301}", 22).'@example.com', SyntaxError::LocalPartTooLong];
        yield 'no at' => ['ada.example.com', SyntaxError::MissingAt];
        yield 'nothing before the at' => ['@example.com', SyntaxError::EmptyLocalPart];
        yield 'local part too long' => [str_repeat('a', 65).'@example.com', SyntaxError::LocalPartTooLong];
        yield 'leading dot' => ['.ada@example.com', SyntaxError::MisplacedDot];
        yield 'trailing dot' => ['ada.@example.com', SyntaxError::MisplacedDot];
        yield 'two dots' => ['a..da@example.com', SyntaxError::MisplacedDot];
        yield 'space' => ['a da@example.com', SyntaxError::InvalidLocalPart];
        yield 'second at' => ['a@da@example.com', SyntaxError::InvalidLocalPart];
        yield 'comma' => ['a,da@example.com', SyntaxError::InvalidLocalPart];
        yield 'control' => ["a\x7Fda@example.com", SyntaxError::InvalidLocalPart];
        yield 'C1 control' => ["a\u{85}da@example.com", SyntaxError::InvalidLocalPart];
        yield 'broken UTF-8' => ["a\xC3da@example.com", SyntaxError::InvalidLocalPart];
        yield 'non-ASCII' => ['éda@example.com', SyntaxError::InternationalLocalPart];
        yield 'non-ASCII and a space' => ['é da@example.com', SyntaxError::InvalidLocalPart];
        yield 'quoted' => ['"ada"@example.com', SyntaxError::QuotedLocalPart];
        yield 'nothing after the at' => ['ada@', SyntaxError::EmptyDomain];
        yield 'IP literal' => ['ada@[192.0.2.1]', SyntaxError::IpLiteral];
        yield 'leading dot in domain' => ['ada@.example.com', SyntaxError::InvalidDomain];
        yield 'trailing dot in domain' => ['ada@example.com.', SyntaxError::InvalidDomain];
        yield 'two dots in domain' => ['ada@example..com', SyntaxError::InvalidDomain];
        yield 'underscore' => ['ada@ex_ample.com', SyntaxError::InvalidDomain];
        yield 'leading hyphen' => ['ada@-example.com', SyntaxError::InvalidDomain];
        yield 'space in domain' => ['ada@exa mple.com', SyntaxError::InvalidDomain];
        yield 'label too long' => ['ada@'.str_repeat('a', 64).'.com', SyntaxError::LabelTooLong];
        yield 'single label' => ['ada@localhost', SyntaxError::SingleLabelDomain];
        yield 'numeric top-level domain' => ['ada@192.0.2.1', SyntaxError::InvalidTopLevelDomain];
        yield 'one-letter top-level domain' => ['ada@example.c', SyntaxError::InvalidTopLevelDomain];
    }

    #[DataProvider('invalid')]
    public function testRefuses(string $input, SyntaxError $reason): void
    {
        try {
            EmailAddress::parse($input);
            self::fail('The address was accepted.');
        } catch (InvalidEmailAddress $invalidEmailAddress) {
            self::assertSame($reason, $invalidEmailAddress->reason);
            self::assertSame('Invalid email address: '.$reason->describe().'.', $invalidEmailAddress->getMessage());
        }
    }

    public function testReportsADomainTooLongAsWritten(): void
    {
        $this->expectExceptionObject(new InvalidEmailAddress(SyntaxError::DomainTooLong));

        DomainName::of(implode('.', array_fill(0, 4, str_repeat('a', 63))).'.com');
    }

    public function testAcceptsANonAsciiPartBeforeTheAtWhenAllowed(): void
    {
        $policy = new SyntaxPolicy()->withInternationalLocalPart();
        // "é" decomposed, normalised to its composed form.
        $address = EmailAddress::parse("e\u{301}lise@example.com", $policy);

        self::assertSame("\u{E9}lise", $address->localPart);
        self::assertTrue($address->requiresSmtpUtf8());
        self::assertSame("\u{E9}lise@example.com", $address->toAscii());
    }

    public function testCountsTheLengthOfANonAsciiPartAfterNormalisation(): void
    {
        $policy = new SyntaxPolicy()->withInternationalLocalPart();
        // 32 decomposed letters are 96 octets, and 64 once composed.
        $address = EmailAddress::parse(str_repeat("e\u{301}", 32).'@example.com', $policy);

        self::assertSame(64, \strlen($address->localPart));
        self::assertSame(str_repeat("\u{E9}", 22), EmailAddress::parse(str_repeat("e\u{301}", 22).'@example.com', $policy)->localPart);

        $this->expectExceptionObject(new InvalidEmailAddress(SyntaxError::LocalPartTooLong));

        EmailAddress::parse(str_repeat("e\u{301}", 32).'a@example.com', $policy);
    }

    public function testStillRefusesControlsAndBrokenTextWhenNonAsciiIsAllowed(): void
    {
        $policy = new SyntaxPolicy()->withInternationalLocalPart();

        foreach (["a\u{85}@example.com", "a\xC3@example.com", 'a b@example.com'] as $input) {
            try {
                EmailAddress::parse($input, $policy);
                self::fail('The address was accepted.');
            } catch (InvalidEmailAddress $invalid) {
                self::assertSame(SyntaxError::InvalidLocalPart, $invalid->reason);
            }
        }
    }

    public function testAcceptsAQuotedPartWhenAllowed(): void
    {
        $policy = new SyntaxPolicy()->withQuotedLocalPart();

        self::assertSame('"ada lovelace"', EmailAddress::parse('"ada lovelace"@example.com', $policy)->localPart);
        self::assertSame('"a\"b\\\\c@d"', EmailAddress::parse('"a\"b\\\\c@d"@example.com', $policy)->localPart);
        self::assertSame('""', EmailAddress::parse('""@example.com', $policy)->localPart);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidQuoted(): iterable
    {
        yield 'unclosed' => ['"ada@example.com'];
        yield 'quote inside' => ['"a"b"@example.com'];
        yield 'text after' => ['"ada"x@example.com'];
        yield 'lone backslash at the end' => ['"ada\"@example.com'];
        yield 'control' => ["\"a\x01\"@example.com"];
        yield 'non-ASCII' => ['"é"@example.com'];
    }

    #[DataProvider('invalidQuoted')]
    public function testRefusesAMalformedQuotedPart(string $input): void
    {
        $this->expectExceptionObject(new InvalidEmailAddress(SyntaxError::InvalidLocalPart));

        EmailAddress::parse($input, new SyntaxPolicy()->withQuotedLocalPart());
    }

    public function testAcceptsANonAsciiQuotedPartWhenBothAreAllowed(): void
    {
        $policy = new SyntaxPolicy()->withQuotedLocalPart()->withInternationalLocalPart();

        self::assertSame('"é l"', EmailAddress::parse('"é l"@example.com', $policy)->localPart);
    }

    public function testAcceptsAnIpLiteralWhenAllowed(): void
    {
        $policy = new SyntaxPolicy()->withIpLiteral();
        $v4 = EmailAddress::parse('ada@[192.0.2.1]', $policy);
        $v6 = EmailAddress::parse('ada@[ipv6:2001:DB8::1]', $policy);

        self::assertSame('[192.0.2.1]', $v4->domain);
        self::assertSame('[192.0.2.1]', $v4->asciiDomain);
        self::assertTrue($v4->isIpLiteral());
        self::assertSame('ada@[IPv6:2001:db8::1]', $v6->toAscii());
        self::assertSame('[IPv6:2001:db8::1]', $v6->domain);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLiterals(): iterable
    {
        yield 'unclosed' => ['ada@[192.0.2.1'];
        yield 'not an address' => ['ada@[example.com]'];
        yield 'IPv6 without its tag' => ['ada@[2001:db8::1]'];
        yield 'IPv4 with the IPv6 tag' => ['ada@[IPv6:192.0.2.1]'];
        yield 'empty' => ['ada@[]'];
        yield 'IPv6 tag without its colon' => ['ada@[IPv6x::1]'];
    }

    #[DataProvider('invalidLiterals')]
    public function testRefusesAMalformedIpLiteral(string $input): void
    {
        $this->expectExceptionObject(new InvalidEmailAddress(SyntaxError::InvalidIpLiteral));

        EmailAddress::parse($input, new SyntaxPolicy()->withIpLiteral());
    }

    public function testEachWithMethodChangesOneChoice(): void
    {
        $default = new SyntaxPolicy();
        $all = $default->withInternationalLocalPart()->withQuotedLocalPart()->withIpLiteral();

        self::assertFalse($default->internationalLocalPart);
        self::assertFalse($default->quotedLocalPart);
        self::assertFalse($default->ipLiteral);
        self::assertTrue($all->internationalLocalPart);
        self::assertTrue($all->quotedLocalPart);
        self::assertTrue($all->ipLiteral);

        $none = $all->withInternationalLocalPart(false)->withQuotedLocalPart(false)->withIpLiteral(false);

        self::assertFalse($none->internationalLocalPart);
        self::assertFalse($none->quotedLocalPart);
        self::assertFalse($none->ipLiteral);
        self::assertTrue($default->withQuotedLocalPart()->withInternationalLocalPart(false)->quotedLocalPart);
        self::assertTrue($default->withIpLiteral()->withQuotedLocalPart(false)->ipLiteral);
        self::assertTrue($default->withInternationalLocalPart()->withIpLiteral(false)->internationalLocalPart);
    }

    public function testDescribesEveryReason(): void
    {
        foreach (SyntaxError::cases() as $reason) {
            self::assertNotSame('', $reason->describe());
        }

        self::assertSame('the domain has no dot', SyntaxError::SingleLabelDomain->describe());
    }
}
