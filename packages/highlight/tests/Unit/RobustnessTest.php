<?php

declare(strict_types=1);

namespace Phil\Highlight\Tests\Unit;

use Phil\Highlight\Highlighter;
use Phil\Highlight\LineOptions;
use Phil\Highlight\Token;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every language on sources it was not written for: nothing is lost, nothing throws, and hostile sources are read in
 * linear time. Not counted for mutation, which the tests of each language do.
 */
#[CoversNothing]
final class RobustnessTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function languages(): iterable
    {
        foreach (Highlighter::default()->languages() as $language) {
            yield $language => [$language];
        }
    }

    #[DataProvider('languages')]
    public function testNoByteIsLost(string $language): void
    {
        $highlighter = Highlighter::default();
        $sources = ['', "\n", '"', "'", '`', '/', '/*', '<!--', '<', '<a', '{{', '{%', '#', '$', '@', '\\', "\xFF\xFE", 'é', "a\r\nb", '```', '[', '*', '_', '|', ':', '-', '#!', '<?php', '?>', '#[', '0x', '1e', '.5', "'\\", 'url(', '#{', '&', '&#', '<![CDATA['];
        $sources[] = implode(' ', $sources);
        $sources[] = implode("\n", $sources);

        foreach ($sources as $source) {
            $tokens = $highlighter->tokenize($source, $language);
            self::assertSame($source, implode('', array_map(static fn (Token $token): string => $token->text, $tokens)), $language.': '.json_encode($source));

            foreach ($tokens as $index => $token) {
                self::assertNotSame('', $token->text);
                self::assertTrue(0 === $index || $tokens[$index - 1]->type !== $token->type, 'adjacent tokens of one type are joined');
            }
        }
    }

    #[DataProvider('languages')]
    public function testLinesKeepTheCode(string $language): void
    {
        $highlighter = Highlighter::default();
        $sources = ['', "\n", "a\n", "\n\n", "a\r\nb", "/*\n*/", "<!--\n-->", "'\n'", "a // [!code ++]\nb # [!code --]\n", "{# [!code highlight:2] #}\nx\ny"];

        foreach ($sources as $source) {
            $html = $highlighter->toHtml($source, $language, new LineOptions());
            self::assertSame($source, html_entity_decode(strip_tags($html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'), $language.': '.json_encode($source));
            self::assertSame(substr_count($source, "\n") + (str_ends_with($source, "\n") ? 0 : 1), substr_count($html, '<span class="hl-line'), $language.': '.json_encode($source));
        }
    }

    #[DataProvider('languages')]
    public function testHostileSourcesAreReadInLinearTime(string $language): void
    {
        $highlighter = Highlighter::default();

        foreach (['[', '*', '`', '<', '{', '"', '/', '#', '- ', 'a: ', '$', '<a b=', '/*', "\\\n", '{{', '![', '_a ', '** '] as $unit) {
            $source = str_repeat($unit, intdiv(10_000, \strlen($unit)));
            $start = hrtime(true);
            $highlighter->tokenize($source, $language);
            self::assertLessThan(1.0, (hrtime(true) - $start) / 1e9, $language.': '.json_encode($unit));
        }
    }
}
