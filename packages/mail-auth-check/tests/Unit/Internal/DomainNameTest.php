<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Tests\Unit\Internal;

use Phil\MailAuthCheck\Exception\InvalidDomain;
use Phil\MailAuthCheck\Internal\DomainName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[CoversClass(DomainName::class)]
#[CoversClass(InvalidDomain::class)]
final class DomainNameTest extends TestCase
{
    public function testADomainIsLowercasedTrimmedAndLosesItsFinalDot(): void
    {
        self::assertSame('mail.example.org', DomainName::toAscii("  Mail.Example.ORG. \n"));
        self::assertSame('a-1.example.org', DomainName::toAscii('a-1.example.org'));
    }

    #[RequiresPhpExtension('intl')]
    public function testAnInternationalisedDomainIsConverted(): void
    {
        self::assertSame('xn--bcher-kva.example', DomainName::toAscii('Bücher.example'));
    }

    #[RequiresPhpExtension('intl')]
    public function testAnInternationalisedDomainThatCannotBeConvertedIsRefused(): void
    {
        $this->expectException(InvalidDomain::class);
        $this->expectExceptionMessageIsOrContains('cannot be converted to ASCII');

        DomainName::toAscii("\u{0661}a.example");
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidDomains(): iterable
    {
        yield 'empty' => ['', '"" is not a valid domain name: it is empty.'];
        yield 'a dot alone' => ['.', '"." is not a valid domain name: it is empty.'];
        yield 'one label' => ['localhost', '"localhost" is not a valid domain name: it needs at least two labels.'];
        yield 'an empty label' => ['a..example', 'the label "" is not 1 to 63 letters, digits or inner hyphens'];
        yield 'a leading hyphen' => ['-a.example', 'the label "-a"'];
        yield 'a trailing hyphen' => ['a-.example', 'the label "a-"'];
        yield 'an underscore' => ['_a.example', 'the label "_a"'];
        yield 'a label of 64' => [str_repeat('a', 64).'.example', 'the label "'.str_repeat('a', 63).'" is not'];
        yield 'a newline inside' => ["a\nb.example", 'the label "a'];
        yield 'too long' => [str_repeat('abcdefghi.', 25).'example', 'it is longer than 253 characters'];
    }

    #[DataProvider('invalidDomains')]
    public function testAnInvalidDomainIsRefused(string $domain, string $message): void
    {
        $this->expectException(InvalidDomain::class);
        $this->expectExceptionMessageIsOrContains($message);

        DomainName::toAscii($domain);
    }

    public function testALabelOf63AndADomainOf253AreAccepted(): void
    {
        $label = str_repeat('a', 63);
        self::assertSame($label.'.example', DomainName::toAscii($label.'.example'));

        $domain = implode('.', [str_repeat('a', 63), str_repeat('b', 63), str_repeat('c', 63), str_repeat('d', 61)]);
        self::assertSame(253, \strlen($domain));
        self::assertSame($domain, DomainName::toAscii($domain));
    }

    public function testTheMessageShowsControlCharactersAsQuestionMarksAndIsCut(): void
    {
        try {
            DomainName::toAscii("x\x01".str_repeat('y', 100));
            self::fail('The domain was accepted.');
        } catch (InvalidDomain $invalidDomain) {
            self::assertStringStartsWith('"x?'.str_repeat('y', 78).'" is not', $invalidDomain->getMessage());
        }
    }

    public function testTheMessageOfIntlNamesTheAsciiForm(): void
    {
        self::assertSame(
            '"b??cher.example" is an internationalised domain name: install ext-intl to convert it, or give its ASCII form (xn--).',
            InvalidDomain::needsIntl('bücher.example')->getMessage(),
        );
    }

    public function testASelectorMayHaveOneLabelOrSeveral(): void
    {
        self::assertSame('s1', DomainName::selector('S1'));
        self::assertSame('mail.2026', DomainName::selector('mail.2026.'));
    }

    public function testASelectorWithAnUnderscoreIsRefused(): void
    {
        $this->expectException(InvalidDomain::class);

        DomainName::selector('_s1');
    }

    public function testADnsNameMayHoldUnderscores(): void
    {
        self::assertSame('_spf.provider.example', DomainName::dnsName('_SPF.Provider.example.'));
        self::assertSame('a_', DomainName::dnsName('a_'));
        self::assertNull(DomainName::dnsName(''));
        self::assertNull(DomainName::dnsName('.'));
        self::assertNull(DomainName::dnsName('a..b'));
        self::assertNull(DomainName::dnsName('-a.b'));
        self::assertNull(DomainName::dnsName('a b.example'));
        self::assertNull(DomainName::dnsName("a.example\n"));
        self::assertNull(DomainName::dnsName(str_repeat('a', 64).'.b'));
        self::assertSame(str_repeat('a', 63).'.b', DomainName::dnsName(str_repeat('a', 63).'.b'));
        self::assertNull(DomainName::dnsName(str_repeat('abcdefghi.', 25).'abcd'));
        self::assertNotNull(DomainName::dnsName(str_repeat('abcdefghi.', 25).'abc'));
    }

    public function testTheRightmostLabelsAndTheirCount(): void
    {
        self::assertSame('example.org', DomainName::rightmost('a.b.example.org', 2));
        self::assertSame('org', DomainName::rightmost('a.b.example.org', 1));
        self::assertSame(4, DomainName::labelCount('a.b.example.org'));
        self::assertSame(1, DomainName::labelCount('org'));
    }
}
