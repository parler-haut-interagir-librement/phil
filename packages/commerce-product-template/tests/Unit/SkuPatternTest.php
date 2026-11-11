<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate\Tests\Unit;

use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplate\SkuPattern;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SkuPattern::class)]
final class SkuPatternTest extends TestCase
{
    public function testGluedPlaceholdersRenderTheCodesSideBySide(): void
    {
        $pattern = SkuPattern::parse('{design}{lang}{version}{size}{colour}');

        self::assertSame('{design}{lang}{version}{size}{colour}', $pattern->pattern);
        self::assertSame(['design', 'lang', 'version', 'size', 'colour'], $pattern->placeholders());
        self::assertSame('NAQ001FOMBK', $pattern->render(['design' => 'NAQ001', 'lang' => 'F', 'version' => 'O', 'size' => 'M', 'colour' => 'BK', 'unused' => 'X']));
        self::assertSame('NAQ001OMBK', $pattern->render(['design' => 'NAQ001', 'lang' => '', 'version' => 'O', 'size' => 'M', 'colour' => 'BK']));
    }

    public function testLiteralsStayBetweenThePlaceholders(): void
    {
        $pattern = SkuPattern::parse('TS-{design}_{size}.{colour}-v2');

        self::assertSame(['design', 'size', 'colour'], $pattern->placeholders());
        self::assertSame('TS-GATS001_XL.WH-v2', $pattern->render(['design' => 'GATS001', 'size' => 'XL', 'colour' => 'WH']));
    }

    public function testAPlaceholderWithoutAValueIsRefused(): void
    {
        $this->expectExceptionObject(InvalidDefinition::because('The SKU pattern "{design}-{size}" has no value for {size}.'));

        SkuPattern::parse('{design}-{size}')->render(['design' => 'A']);
    }

    public function testAPatternTakesUpToItsLength(): void
    {
        $pattern = '{design}'.str_repeat('x', SkuPattern::MAX_LENGTH - 8);

        self::assertSame($pattern, SkuPattern::parse($pattern)->pattern);
        self::assertSame(['a'.str_repeat('b', 31), 'design'], SkuPattern::parse('{a'.str_repeat('b', 31).'}{design}')->placeholders());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedPatterns(): iterable
    {
        $syntax = static fn (string $pattern): string => \sprintf('"%s" is not a SKU pattern: letters, digits, ".", "_", "-" and placeholders such as {size}.', $pattern);

        yield 'empty' => ['', 'A SKU pattern holds 1 to 128 characters.'];
        yield 'too long' => ['{design}'.str_repeat('x', 121), 'A SKU pattern holds 1 to 128 characters.'];
        yield 'a space' => ['{design} {size}', $syntax('{design} {size}')];
        yield 'an open brace' => ['{design}{size', $syntax('{design}{size')];
        yield 'a closing brace' => ['{design}size}', $syntax('{design}size}')];
        yield 'an empty placeholder' => ['{design}{}', $syntax('{design}{}')];
        yield 'an uppercase placeholder' => ['{design}{Size}', $syntax('{design}{Size}')];
        yield 'a placeholder starting with a digit' => ['{design}{1size}', $syntax('{design}{1size}')];
        yield 'a long placeholder' => ['{design}{a'.str_repeat('b', 32).'}', $syntax('{design}{a'.str_repeat('b', 32).'}')];
        yield 'nested braces' => ['{design}{{size}}', $syntax('{design}{{size}}')];
        yield 'a line after' => ["{design}\n", $syntax("{design}\n")];
        yield 'twice' => ['{design}{size}{size}', 'The SKU pattern "{design}{size}{size}" names {size} twice.'];
        yield 'no design' => ['{size}{colour}', 'The SKU pattern "{size}{colour}" names {design}: two designs never share a SKU.'];
        yield 'design as a literal' => ['design{size}', 'The SKU pattern "design{size}" names {design}: two designs never share a SKU.'];
    }

    #[DataProvider('refusedPatterns')]
    public function testAPatternRefusesWhatIsNotOne(string $pattern, string $message): void
    {
        $this->expectExceptionObject(InvalidDefinition::because($message));

        SkuPattern::parse($pattern);
    }
}
