<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Domain\Capability;

use Phil\ContentBundle\Domain\Capability\CapabilityText;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CapabilityText::class)]
#[CoversClass(InvalidContent::class)]
final class CapabilityTextTest extends TestCase
{
    #[DataProvider('validTexts')]
    public function testATextIsKeptAsGiven(string $text): void
    {
        self::assertSame($text, CapabilityText::text($text, 'The field'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validTexts(): iterable
    {
        yield 'a word' => ['Bonjour'];
        yield 'accents' => ['Été à Montréal'];
        yield 'a line break' => ["first\nsecond"];
        yield 'a Windows line break' => ["first\r\nsecond"];
        yield 'a tab' => ["first\tsecond"];
    }

    #[DataProvider('invalidTexts')]
    public function testATextIsRefused(string $text, string $reason): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('The field '.$reason);

        CapabilityText::text($text, 'The field');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidTexts(): iterable
    {
        yield 'empty' => ['', 'must be trimmed and not empty'];
        yield 'blank' => ['   ', 'must be trimmed and not empty'];
        yield 'a leading space' => [' Bonjour', 'must be trimmed and not empty'];
        yield 'a trailing line break' => ["Bonjour\n", 'must be trimmed and not empty'];
        yield 'invalid UTF-8' => ["caf\xC3", 'is not valid UTF-8'];
        yield 'NUL' => ["a\x00b", 'contains a control character'];
        yield 'backspace' => ["a\x08b", 'contains a control character'];
        yield 'vertical tab' => ["a\x0Bb", 'contains a control character'];
        yield 'form feed' => ["a\x0Cb", 'contains a control character'];
        yield 'shift out' => ["a\x0Eb", 'contains a control character'];
        yield 'unit separator' => ["a\x1Fb", 'contains a control character'];
        yield 'delete' => ["a\x7Fb", 'contains a control character'];
    }

    public function testALineOfTheColumnLengthInCharactersIsKept(): void
    {
        $line = str_repeat('é', CapabilityText::LINE_LENGTH);

        self::assertSame($line, CapabilityText::line($line, 'The field'));
    }

    #[DataProvider('invalidLines')]
    public function testALineIsRefused(string $line, string $reason): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('The field '.$reason);

        CapabilityText::line($line, 'The field');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidLines(): iterable
    {
        yield 'a line break' => ["first\nsecond", 'is a single line'];
        yield 'a carriage return' => ["first\rsecond", 'is a single line'];
        yield 'a tab' => ["first\tsecond", 'is a single line'];
        yield 'one character too many' => [str_repeat('a', 256), 'is longer than 255 characters'];
        yield 'one accented character too many' => [str_repeat('é', 256), 'is longer than 255 characters'];
        yield 'what a text refuses' => [' Bonjour', 'must be trimmed and not empty'];
    }

    #[DataProvider('validReferences')]
    public function testAReferenceIsKeptAsGiven(string $reference): void
    {
        self::assertSame($reference, CapabilityText::reference($reference, 'The image'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validReferences(): iterable
    {
        yield 'a path' => ['/images/cover.png'];
        yield 'a URL' => ['https://cdn.example.org/cover.png?w=800'];
        yield 'a media identifier' => ['media:0192f3c4'];
        yield 'one character' => ['a'];
        yield 'the longest' => [str_repeat('a', 2048)];
    }

    #[DataProvider('invalidReferences')]
    public function testAReferenceIsRefused(string $reference): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('The image must be 1 to 2048 printable ASCII characters');

        CapabilityText::reference($reference, 'The image');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidReferences(): iterable
    {
        yield 'empty' => [''];
        yield 'a space inside' => ['/images/my cover.png'];
        yield 'a leading space' => [' /cover.png'];
        yield 'a trailing space' => ['/cover.png '];
        yield 'a trailing line break' => ["/cover.png\n"];
        yield 'an accent' => ['/images/été.png'];
        yield 'one character too many' => [str_repeat('a', 2049)];
    }
}
