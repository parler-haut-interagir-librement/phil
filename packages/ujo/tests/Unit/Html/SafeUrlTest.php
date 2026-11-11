<?php

declare(strict_types=1);

namespace Phil\Ujo\Tests\Unit\Html;

use Phil\Ujo\Html\AttributeValue;
use Phil\Ujo\Html\LanguageTag;
use Phil\Ujo\Html\SafeUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SafeUrl::class)]
#[CoversClass(LanguageTag::class)]
#[CoversClass(AttributeValue::class)]
final class SafeUrlTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function links(): iterable
    {
        yield 'https' => ['https://example.com', true];
        yield 'http with a path' => ['http://example.com/a?b#c', true];
        yield 'upper-case scheme' => ['HTTPS://example.com', true];
        yield 'query right after the host' => ['https://example.com?a', true];
        yield 'fragment right after the host' => ['https://example.com#a', true];
        yield 'mailto' => ['mailto:a@example.com', true];
        yield 'tel' => ['tel:+15145550000', true];
        yield 'tel with separators and a parameter' => ['tel:+1-514-555-0000;ext=12', true];
        yield 'tel with letters' => ['tel:+1-514-CALL', false];
        yield 'tel without number' => ['tel:', false];
        yield 'sms' => ['sms:+15145550000', true];
        yield 'sms with a body' => ['sms:+15145550000?body=Hello%20there', true];
        yield 'sms with a script' => ['sms:+1;javascript:x()', false];
        yield 'geo' => ['geo:45.5017,-73.5673', true];
        yield 'geo with altitude and a parameter' => ['geo:-45.5,173.5,120;u=35', true];
        yield 'geo out of form' => ['geo:north', false];
        yield 'xmpp' => ['xmpp:someone@example.com', true];
        yield 'matrix' => ['matrix:u/someone:example.org', true];
        yield 'viber' => ['viber://chat?number=%2B15145550000', true];
        yield 'relative path' => ['/a/b', true];
        yield 'relative file' => ['a/b:c', true];
        yield 'fragment' => ['#top', true];
        yield 'query' => ['?page=2', true];
        yield 'empty' => ['', false];
        yield 'javascript' => ['javascript:alert(1)', false];
        yield 'upper-case javascript' => ['JavaScript:alert(1)', false];
        yield 'data' => ['data:text/html,x', false];
        yield 'leading space' => [' https://example.com', false];
        yield 'inner space' => ['https://exa mple.com', false];
        yield 'tab in the scheme' => ["java\tscript:x", false];
        yield 'control character' => ["https://example.com/\x01", false];
        yield 'delete character' => ["https://example.com/\x7F", false];
        yield 'backslash' => ['/\\example.com', false];
        yield 'protocol-relative' => ['//example.com', false];
        yield 'http without host' => ['http:/example.com', false];
        yield 'http with credentials' => ['https://user@example.com', false];
        yield 'http with an empty host' => ['https://', false];
        yield 'unknown scheme' => ['ftp://example.com', false];
        yield 'scheme with digits and signs' => ['web+a.b-1:x', false];
    }

    #[DataProvider('links')]
    public function testALinkIsSafeOrNot(string $url, bool $safe): void
    {
        self::assertSame($safe, SafeUrl::isSafe($url));
        self::assertSame($safe ? $url : null, AttributeValue::Link->clean($url));
    }

    public function testASourceIsHttpHttpsOrRelative(): void
    {
        self::assertTrue(SafeUrl::isSafe('https://example.com/a.png', SafeUrl::MEDIA));
        self::assertFalse(SafeUrl::isSafe('mailto:a@example.com', SafeUrl::MEDIA));
        self::assertFalse(SafeUrl::isSafe('/a.png', SafeUrl::MEDIA, false));
        self::assertTrue(SafeUrl::isSafe('mailto:a@example.com', ['mailto'], false));
        self::assertSame('/a.png', AttributeValue::Media->clean('/a.png'));
        self::assertNull(AttributeValue::Media->clean('tel:1'));
        self::assertSame(['http', 'https', 'mailto', 'tel', 'sms', 'geo', 'xmpp', 'matrix', 'viber'], SafeUrl::LINK);
        self::assertSame('https', SafeUrl::scheme('HTTPS://example.com'));
        self::assertSame('web+a.b-1', SafeUrl::scheme('web+a.b-1:x'));
        self::assertNull(SafeUrl::scheme('/a:b'));
        self::assertSame(['http', 'https'], SafeUrl::MEDIA);
    }

    /**
     * @return iterable<string, array{AttributeValue, string, ?string}>
     */
    public static function values(): iterable
    {
        yield 'token' => [AttributeValue::Token, 'fn-1_A', 'fn-1_A'];
        yield 'language' => [AttributeValue::Language, 'fr-CA', 'fr-CA'];
        yield 'language with a script and a region' => [AttributeValue::Language, 'zh-Hant-TW', 'zh-Hant-TW'];
        yield 'language with a variant, an extension and a private part' => [AttributeValue::Language, 'de-CH-1901-u-co-phonebk-x-old', 'de-CH-1901-u-co-phonebk-x-old'];
        yield 'language of private use' => [AttributeValue::Language, 'x-klingon', 'x-klingon'];
        yield 'language with an extended subtag' => [AttributeValue::Language, 'zh-yue-HK', 'zh-yue-HK'];
        yield 'language of a region in digits' => [AttributeValue::Language, 'es-419', 'es-419'];
        yield 'language with an underscore' => [AttributeValue::Language, 'fr_CA', null];
        yield 'language of one letter' => [AttributeValue::Language, 'f', null];
        yield 'language with a new line' => [AttributeValue::Language, "en\n", null];
        yield 'too long language' => [AttributeValue::Language, 'en'.str_repeat('-abcdefgh', 7), null];
        yield 'direction' => [AttributeValue::Direction, 'RTL', 'rtl'];
        yield 'automatic direction' => [AttributeValue::Direction, 'auto', 'auto'];
        yield 'other direction' => [AttributeValue::Direction, 'up', null];
        yield 'keyword' => [AttributeValue::Keyword, '0.2em', '0.2em'];
        yield 'long keyword' => [AttributeValue::Keyword, str_repeat('a', 32), str_repeat('a', 32)];
        yield 'too long keyword' => [AttributeValue::Keyword, str_repeat('a', 33), null];
        yield 'keyword with a space' => [AttributeValue::Keyword, 'a b', null];
        yield 'long token' => [AttributeValue::Token, str_repeat('a', 64), str_repeat('a', 64)];
        yield 'too long token' => [AttributeValue::Token, str_repeat('a', 65), null];
        yield 'token with a space' => [AttributeValue::Token, 'a b', null];
        yield 'token with a new line' => [AttributeValue::Token, "a\n", null];
        yield 'empty token' => [AttributeValue::Token, '', null];
        yield 'reference' => [AttributeValue::Reference, 'user:42', 'user:42'];
        yield 'reference with a path' => [AttributeValue::Reference, 'fa_b-c:a/b.c-d_E9', 'fa_b-c:a/b.c-d_E9'];
        yield 'long reference' => [AttributeValue::Reference, 'a'.str_repeat('b', 31).':'.str_repeat('c', 128), 'a'.str_repeat('b', 31).':'.str_repeat('c', 128)];
        yield 'reference of too long a kind' => [AttributeValue::Reference, 'a'.str_repeat('b', 32).':c', null];
        yield 'reference of too long an identifier' => [AttributeValue::Reference, 'a:'.str_repeat('c', 129), null];
        yield 'reference without kind' => [AttributeValue::Reference, ':42', null];
        yield 'reference of an upper-case kind' => [AttributeValue::Reference, 'User:42', null];
        yield 'reference without identifier' => [AttributeValue::Reference, 'user:', null];
        yield 'reference with a new line' => [AttributeValue::Reference, "user:4\n", null];
        yield 'text' => [AttributeValue::Text, 'a "quoted" <text>', 'a "quoted" <text>'];
        yield 'long text' => [AttributeValue::Text, str_repeat('é', 1000), str_repeat('é', 1000)];
        yield 'too long text' => [AttributeValue::Text, str_repeat('é', 1001), null];
        yield 'target' => [AttributeValue::Target, '_BLANK', '_blank'];
        yield 'other target' => [AttributeValue::Target, '_top', null];
        yield 'relations' => [AttributeValue::Rel, 'NoFollow  ugc evil external noopener noreferrer sponsored', 'nofollow noopener noreferrer ugc sponsored external'];
        yield 'unknown relations' => [AttributeValue::Rel, 'evil', null];
        yield 'length' => [AttributeValue::Length, '-12.125px', '-12.125px'];
        yield 'lengths' => [AttributeValue::Length, '9999em', '9999em'];
        yield 'length units' => [AttributeValue::Length, '1rem', '1rem'];
        yield 'percentage' => [AttributeValue::Length, '50%', '50%'];
        yield 'other units' => [AttributeValue::Length, '1mm', '1mm'];
        yield 'centimetres' => [AttributeValue::Length, '1cm', '1cm'];
        yield 'inches' => [AttributeValue::Length, '1in', '1in'];
        yield 'points' => [AttributeValue::Length, '1pt', '1pt'];
        yield 'characters' => [AttributeValue::Length, '1ch', '1ch'];
        yield 'length without unit' => [AttributeValue::Length, '12', null];
        yield 'too long length' => [AttributeValue::Length, '10000px', null];
        yield 'too precise length' => [AttributeValue::Length, '1.1234px', null];
        yield 'length with a new line' => [AttributeValue::Length, "1px\n", null];
        yield 'number' => [AttributeValue::Number, '12345', '12345'];
        yield 'too long number' => [AttributeValue::Number, '123456', null];
        yield 'negative number' => [AttributeValue::Number, '-1', null];
        yield 'number with a new line' => [AttributeValue::Number, "1\n", null];
    }

    #[DataProvider('values')]
    public function testAnAttributeValueIsCleanedOrRefused(AttributeValue $kind, string $value, ?string $clean): void
    {
        self::assertSame($clean, $kind->clean($value));
    }
}
