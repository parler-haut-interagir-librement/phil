<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit\Domain;

use Phil\LicensingBundle\Domain\Exception\InvalidLicensing;
use Phil\LicensingBundle\Domain\Text;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Text::class)]
#[CoversClass(InvalidLicensing::class)]
final class TextTest extends TestCase
{
    public function testATextIsTrimmedAndABlankOneIsLeftOut(): void
    {
        self::assertSame('a b', Text::optional("  a b \n", 10, 'title'));
        self::assertNull(Text::optional(" \t\n", 10, 'title'));
        self::assertNull(Text::optional(null, 10, 'title'));
        self::assertSame('a', Text::required(' a ', 10, 'title'));
    }

    public function testARequiredTextIsGiven(): void
    {
        $this->expectExceptionObject(InvalidLicensing::because('The title is required.'));

        Text::required('  ', 10, 'title');
    }

    public function testALengthIsCountedInCharacters(): void
    {
        self::assertSame('ééé', Text::optional('ééé', 3, 'title'));

        $this->expectExceptionObject(InvalidLicensing::because('The title holds 3 characters at most.'));

        Text::optional('éééé', 3, 'title');
    }

    public function testLineBreaksAndTabsAreKept(): void
    {
        self::assertSame("a\tb\nc\r\nd", Text::optional("a\tb\nc\r\nd", 20, 'notice'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedTexts(): iterable
    {
        yield 'invalid UTF-8' => ["a\xFFb"];
        yield 'NUL' => ["a\x00b"];
        yield 'backspace' => ["a\x08b"];
        yield 'vertical tab' => ["a\x0Bb"];
        yield 'form feed' => ["a\x0Cb"];
        yield 'shift out' => ["a\x0Eb"];
        yield 'unit separator' => ["a\x1Fb"];
        yield 'delete' => ["a\x7Fb"];
    }

    #[DataProvider('refusedTexts')]
    public function testControlCharactersAndInvalidTextAreRefused(string $text): void
    {
        $this->expectExceptionObject(InvalidLicensing::because('The notice is UTF-8 text without control characters.'));

        Text::optional($text, 20, 'notice');
    }

    public function testAnAddressIsAbsoluteHttpOrHttps(): void
    {
        self::assertNull(Text::url(null, 'address'));
        self::assertNull(Text::url(' ', 'address'));
        self::assertSame('https://example.com/a?b=c', Text::url(' https://example.com/a?b=c ', 'address'));
        self::assertSame('HTTP://example.com', Text::url('HTTP://example.com', 'address'));
        self::assertSame('https://bücher.example/lizenz', Text::url('https://bücher.example/lizenz', 'address'), 'An international host is accepted.');
        self::assertSame('https://example.com/'.str_repeat('a', 2028), Text::url('https://example.com/'.str_repeat('a', 2028), 'address'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedAddresses(): iterable
    {
        yield 'other scheme' => ['ftp://example.com', 'The address is an absolute http or https address.'];
        yield 'script' => ['javascript:alert(1)', 'The address is an absolute http or https address.'];
        yield 'relative' => ['/licenses/orc', 'The address is an absolute http or https address.'];
        yield 'malformed' => ['https://', 'The address is an absolute http or https address.'];
        yield 'user information' => ['https://example.com@evil.example/', 'The address is an absolute http or https address.'];
        yield 'a password' => ['https://user:secret@example.com/', 'The address is an absolute http or https address.'];
        yield 'a backslash after the scheme' => ['https://\\evil.example/', 'The address is an absolute http or https address.'];
        yield 'an IP address' => ['http://192.168.0.1/license', 'The address is an absolute http or https address.'];
        yield 'localhost' => ['http://localhost/license', 'The address is an absolute http or https address.'];
        yield 'a space' => ['https://example.com/a b', 'The address is an absolute http or https address.'];
        yield 'too long' => ['https://example.com/'.str_repeat('a', 2029), 'The address holds 2048 characters at most.'];
    }

    #[DataProvider('refusedAddresses')]
    public function testOtherAddressesAreRefused(string $url, string $message): void
    {
        $this->expectExceptionObject(InvalidLicensing::because($message));

        Text::url($url, 'address');
    }

    public function testACodeIsShortLowercaseAndPlain(): void
    {
        self::assertSame('cc-by-4.0', Text::code('cc-by-4.0', 'license code'));
        self::assertSame('0', Text::code('0', 'license code'));
        self::assertSame(str_repeat('a', 64), Text::code(str_repeat('a', 64), 'license code'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['CC-BY'];
        yield 'leading hyphen' => ['-orc'];
        yield 'leading dot' => ['.orc'];
        yield 'space' => ['cc by'];
        yield 'trailing line' => ["orc\n"];
        yield 'leading text' => ["x\norc"];
        yield 'too long' => [str_repeat('a', 65)];
    }

    #[DataProvider('refusedCodes')]
    public function testOtherCodesAreRefused(string $code): void
    {
        $this->expectExceptionObject(InvalidLicensing::because(\sprintf('The license code "%s" is 1 to 64 lowercase letters, digits, dots and hyphens.', $code)));

        Text::code($code, 'license code');
    }
}
