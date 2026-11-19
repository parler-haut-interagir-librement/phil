<?php

declare(strict_types=1);

namespace Phil\MimeReader\Tests\Unit;

use Phil\MimeReader\HeaderValue;
use Phil\MimeReader\Internal\Charset;
use Phil\MimeReader\Internal\EncodedWords;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HeaderValue::class)]
#[CoversClass(EncodedWords::class)]
#[CoversClass(Charset::class)]
final class HeaderValueTest extends TestCase
{
    public function testTheValueAndItsParametersAreRead(): void
    {
        $value = HeaderValue::parse(' multipart/mixed ;  Boundary="a;b\"c" ; charset = utf-8 ; novalue; =x; ; bad name=1');

        self::assertSame('multipart/mixed', $value->value);
        self::assertSame(['boundary' => 'a;b"c', 'charset' => 'utf-8'], $value->parameters);
        self::assertSame('a;b"c', $value->parameter('BOUNDARY'));
        self::assertNull($value->parameter('missing'));
        self::assertSame([], new HeaderValue('x')->parameters);
    }

    public function testAQuoteIsRemovedOnlyWhenItWrapsTheWholeValue(): void
    {
        self::assertSame(['c' => '', 'd' => 'a\\b', 'e' => '"; a="x', 'b' => 'y'], HeaderValue::parse('v; c=""; d="a\\\\b"; e="; a="x; b=y')->parameters);
    }

    public function testAnEmptyTextGivesAnEmptyValue(): void
    {
        self::assertEquals(new HeaderValue(''), HeaderValue::parse(''));
    }

    public function testSectionsAreJoinedInTheirOrder(): void
    {
        $value = HeaderValue::parse('attachment; filename*1="two "; filename*0="one "; filename*2=three');

        self::assertSame(['filename' => 'one two three'], $value->parameters);
    }

    public function testExtendedSectionsAreDecodedFromTheirCharacterSet(): void
    {
        $value = HeaderValue::parse("attachment; filename*0*=iso-8859-1'fr'caf%E9%20; filename*1=\"cr%E8me\"; title*=UTF-8''%C3%A9t%C3%A9; plain*=''a%20b");

        self::assertSame(['filename' => 'café cr%E8me', 'title' => 'été', 'plain' => 'a b'], $value->parameters);
    }

    public function testTheCharacterSetIsReadFromTheFirstSectionOnly(): void
    {
        $value = HeaderValue::parse("a; n*0*=x; n*1*=utf-8''%C3%A9");

        self::assertSame(['n' => "xutf-8''é"], $value->parameters);
    }

    public function testASingleExtendedValueWithoutCharacterSetIsPercentDecoded(): void
    {
        self::assertSame(['n' => 'a b'], HeaderValue::parse('a; n*=a%20b')->parameters);
    }

    public function testEncodedWordsInAParameterAreDecoded(): void
    {
        self::assertSame(['filename' => 'café.txt'], HeaderValue::parse('inline; filename="=?iso-8859-1?q?caf=E9.txt?="')->parameters);
    }

    public function testThePlainAndSectionedFormsOfANameCountOnceTowardTheLimit(): void
    {
        $value = HeaderValue::parse('v; a=1; a*0=2; b=3; a=4', 1);

        self::assertSame(['a' => '2'], $value->parameters);
        self::assertSame(['a' => '4', 'b' => '3'], HeaderValue::parse('v; a=1; b=3; a=4', 2)->parameters);
        self::assertSame(['a' => '1', 'b' => '2'], HeaderValue::parse('v; a=1; b=2; c=3', 2)->parameters);
    }

    public function testASectionNumberHasAtMostThreeDigits(): void
    {
        self::assertSame(['n' => 'x'], HeaderValue::parse('v; n*999=x; n*1000=y')->parameters);
    }
}
