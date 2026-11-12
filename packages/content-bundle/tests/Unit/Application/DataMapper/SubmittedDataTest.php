<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\DataMapper;

use Phil\ContentBundle\Application\DataMapper\SubmittedData;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubmittedData::class)]
final class SubmittedDataTest extends TestCase
{
    public function testAKeySetToNullIsPresent(): void
    {
        self::assertTrue(SubmittedData::has(['title' => null], 'title'));
        self::assertFalse(SubmittedData::has([], 'title'));
    }

    public function testAStringIsTrimmedAndABlankOneIsNull(): void
    {
        self::assertSame('Bonjour', SubmittedData::string(['title' => "  Bonjour\n"], 'title'));
        self::assertNull(SubmittedData::string(['title' => '   '], 'title'));
        self::assertNull(SubmittedData::string(['title' => null], 'title'));
        self::assertNull(SubmittedData::string([], 'title'));
    }

    public function testAnUntrimmedStringIsKeptAsWrittenAndABlankOneIsNull(): void
    {
        self::assertSame("    code\n", SubmittedData::untrimmedString(['body' => "    code\n"], 'body'));
        self::assertNull(SubmittedData::untrimmedString(['body' => "\n  \n"], 'body'));
    }

    public function testAStringOfTheWrongTypeIsRefused(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('"title" must be a string or null; got int.');

        SubmittedData::string(['title' => 42], 'title');
    }

    public function testABooleanIsReadAsItIs(): void
    {
        self::assertTrue(SubmittedData::bool(['flag' => true], 'flag'));
        self::assertFalse(SubmittedData::bool(['flag' => false], 'flag'));
        self::assertNull(SubmittedData::bool(['flag' => null], 'flag'));
    }

    public function testABooleanOfTheWrongTypeIsRefused(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('"flag" must be a boolean or null; got string.');

        SubmittedData::bool(['flag' => 'yes'], 'flag');
    }

    public function testAListIsReadFromAnArrayOrACommaSeparatedString(): void
    {
        self::assertSame(['larp', 'events'], SubmittedData::stringList(['k' => [' larp', '', 'events ']], 'k'));
        self::assertSame(['larp', 'events'], SubmittedData::stringList(['k' => 'larp, , events'], 'k'));
        self::assertSame([], SubmittedData::stringList(['k' => null], 'k'));
    }

    public function testAListOfTheWrongTypeIsRefused(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('"k" must be a list of strings');

        SubmittedData::stringList(['k' => 42], 'k');
    }

    public function testAListHoldingSomethingOtherThanStringsIsRefused(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('"k" must hold strings only; got int.');

        SubmittedData::stringList(['k' => ['larp', 42]], 'k');
    }

    public function testAMapIsReadAsItIs(): void
    {
        self::assertSame(['url' => 'https://example.org/'], SubmittedData::map(['link' => ['url' => 'https://example.org/']], 'link'));
        self::assertNull(SubmittedData::map(['link' => null], 'link'));
    }

    public function testAMapOfTheWrongTypeIsRefused(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('"link" must be an array or null; got string.');

        SubmittedData::map(['link' => 'https://example.org/'], 'link');
    }
}
