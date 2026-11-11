<?php

declare(strict_types=1);

namespace Phil\Markup\Tests\Unit\Reader;

use Closure;
use Dom\HTMLDocument;
use Phil\Markup\Reader\BBCode\BBCodeReader;
use Phil\Markup\Reader\Creole\CreoleReader;
use Phil\Markup\Reader\DokuWiki\DokuWikiReader;
use Phil\Markup\Reader\Html\HtmlReader;
use Phil\Markup\Reader\MediaWiki\MediaWikiReader;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Type\BlockTypeRegistry;
use Phil\Ujo\Type\StandardTypes;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * How the time the readers of BBCode, of the wikis and of HTML take grows on sources written to be slow. These tests
 * attribute no coverage: they take seconds, and a mutant they would kill makes a reading slower, not wrong, which the
 * tests of each reader do not see — those mutants are named in the package's `phil-dev.php`.
 *
 * Each shape is read at an eighth and at half of its 100 KB, and the two times are compared rather than held to a
 * number of seconds: four times as much text takes about four times as long to a reading linear in its length, sixteen
 * times as long to a quadratic one, whatever the speed or the load of the machine. Each time is the processor time of
 * the fastest of a few readings of the two sources in turn, and a time under a few milliseconds counts as that floor,
 * where the noise of a busy machine outweighs the reading.
 */
#[CoversNothing]
final class WikiLinearityTest extends TestCase
{
    /** How much more text the second reading gets. */
    private const int GROWTH = 4;

    /** The most the time may grow by: under a quadratic reading's 16, well over a linear one's 4. */
    private const float MAXIMUM_RATIO = 12.0;

    /** The most a shape that grows faster than linearly may grow by: far under a cubic reading's 64. */
    private const float SUPERLINEAR_RATIO = 20.0;

    /** The time under which a reading counts as this floor, in seconds. */
    private const float FLOOR = 0.005;

    /** How many readings each time is the fastest of. */
    private const int READINGS = 3;

    /**
     * The normalizer of the readers, without the limits on blocks and HTML a stored document is held to: the sources
     * are long on purpose, and their reading is what is measured.
     */
    private static function normalizer(): Normalizer
    {
        return new Normalizer(BlockTypeRegistry::default()->with(StandardTypes::raw()), maxBlocks: \PHP_INT_MAX, maxHtmlLength: \PHP_INT_MAX, maxRawLength: \PHP_INT_MAX);
    }

    /**
     * Sources written to be slow — delimiters left open by the thousand, containers nested deep or never closed, lists,
     * tables, footnotes, links and headings by the thousand — take a time that grows with their length, not with its
     * square.
     */
    public function testMediaWikiIsReadInLinearTime(): void
    {
        $this->assertGrowsLinearly(static fn (string $source): mixed => new MediaWikiReader(normalizer: self::normalizer())->read($source), [
            'containers left open' => [$this->repeat("<note>\n"), 15_000],
            'containers on one line' => [$this->repeat('<note>a</note>'), 7_000],
            'columns' => [$this->repeat("<columns><column>a<column>b</columns>\n"), 2_500],
            'tags left open' => [$this->repeat('<footnote>a <u>b <sup>'), 4_500],
            'tags never closed' => [$this->repeat('<b'), 50_000],
            'angle brackets' => [$this->repeat('<'), 100_000],
            'closing tags' => [$this->repeat('</x', '<note>'), 33_000],
            'raw left open' => [$this->repeat("<code>\n"), 14_000],
            'addresses' => [$this->repeat('https://e.x/a '), 7_500],
            'links left open' => [$this->repeat('[[a '), 25_000],
            'links nested' => [$this->repeat('[[a|', '', str_repeat(']]', 10)), 25_000],
            'paragraphs' => [$this->repeat("a\n\n"), 33_000],
            'a quote of paragraphs' => [$this->repeat("a\n\n", "<quote>\n"), 33_000],
            'apostrophes' => [$this->repeat("'' ''' "), 15_000],
            'templates left open' => [$this->repeat('{{a|'), 25_000],
            'lists' => [$this->repeat("*# a\n"), 20_000],
            'tables' => [$this->repeat("| a || b\n|-\n", "{|\n"), 9_000],
            'a long row' => [$this->repeat("| a\nb\n", "{|\n"), 17_000],
            'items' => [$this->repeat("* a\n"), 25_000],
            'entities' => [$this->repeat('&abcdefgh'), 12_000],
            'comments' => [$this->repeat('<!-- a'), 20_000],
        ]);
    }

    /**
     * Sources written to be slow — delimiters left open by the thousand, containers nested deep or never closed, lists,
     * tables, footnotes, links and headings by the thousand — take a time that grows with their length, not with its
     * square.
     */
    public function testDokuWikiIsReadInLinearTime(): void
    {
        $this->assertGrowsLinearly(static fn (string $source): mixed => new DokuWikiReader(normalizer: self::normalizer())->read($source), [
            'containers left open' => [$this->repeat("<note>\n"), 15_000],
            'containers on one line' => [$this->repeat('<note>a</note>'), 7_000],
            'columns' => [$this->repeat("<columns><column>a<column>b</columns>\n"), 2_500],
            'tags left open' => [$this->repeat('<footnote>a <u>b <sup>'), 4_500],
            'tags never closed' => [$this->repeat('<b'), 50_000],
            'angle brackets' => [$this->repeat('<'), 100_000],
            'closing tags' => [$this->repeat('</x', '<note>'), 33_000],
            'raw left open' => [$this->repeat("<code>\n"), 14_000],
            'addresses' => [$this->repeat('https://e.x/a '), 7_500],
            'links left open' => [$this->repeat('[[a '), 25_000],
            'links nested' => [$this->repeat('[[a|', '', str_repeat(']]', 10)), 25_000],
            'paragraphs' => [$this->repeat("a\n\n"), 33_000],
            'a quote of paragraphs' => [$this->repeat("a\n\n", "<quote>\n"), 33_000],
            'emphasis' => [$this->repeat("** // __ '' "), 9_000],
            'footnotes left open' => [$this->repeat('((a '), 25_000],
            'media left open' => [$this->repeat('{{a '), 25_000],
            'lists' => [$this->repeat("  * a\n    - b\n"), 9_000],
            'quotes' => [$this->repeat("> a\n"), 25_000],
            'definitions' => [$this->repeat("  : d\n", "  = t\n"), 17_000],
            'tables' => [$this->repeat("^ a | [[b ^ {{c |\n"), 6_000],
            // A row of links left open takes a time that grows close to the square of its length.
            'a table row' => [$this->repeat('[[a|', '|'), 25_000, self::SUPERLINEAR_RATIO],
        ]);
    }

    /**
     * Sources written to be slow — delimiters left open by the thousand, containers nested deep or never closed, lists,
     * tables, footnotes, links and headings by the thousand — take a time that grows with their length, not with its
     * square.
     */
    public function testCreoleIsReadInLinearTime(): void
    {
        $this->assertGrowsLinearly(static fn (string $source): mixed => new CreoleReader(normalizer: self::normalizer())->read($source), [
            'containers left open' => [$this->repeat("<<note>>\n"), 15_000],
            'containers on one line' => [$this->repeat('<<note>>a<</note>>'), 7_000],
            'columns' => [$this->repeat("<<columns>><<column>>a<<column>>b<</columns>>\n"), 2_500],
            'tags left open' => [$this->repeat('<<footnote>>a <<u>>b <<sup>>'), 4_500],
            'tags never closed' => [$this->repeat('<<b'), 50_000],
            'angle brackets' => [$this->repeat('<'), 100_000],
            'closing tags' => [$this->repeat('<</x', '<<note>>'), 33_000],
            'raw left open' => [$this->repeat("<<code>>\n"), 14_000],
            'addresses' => [$this->repeat('https://e.x/a '), 7_500],
            'links left open' => [$this->repeat('[[a '), 25_000],
            'links nested' => [$this->repeat('[[a|', '', str_repeat(']]', 10)), 25_000],
            'paragraphs' => [$this->repeat("a\n\n"), 33_000],
            'a quote of paragraphs' => [$this->repeat("a\n\n", "<<quote>>\n"), 33_000],
            'emphasis' => [$this->repeat('** // ## ^^ ,, __ '), 6_000],
            'nowiki left open' => [$this->repeat('{{{a '), 20_000],
            'escapes' => [$this->repeat('~'), 100_000],
            'lists' => [$this->repeat("* a\n## b\n"), 12_000],
            'tables' => [$this->repeat("|= a | b |\n"), 9_000],
        ]);
    }

    /**
     * Sources written to be slow — elements nested deep, footnotes and images by the thousand — take a time that grows
     * with their length, not with its square. The HTML5 parser of PHP looks through the open elements at each tag, as
     * the standard says, and so takes a time that grows with the square of the depth: the reader may grow as much as
     * the parser beneath it does on the same source, never more.
     */
    public function testHtmlIsReadInLinearTime(): void
    {
        $this->assertGrowsLinearly(static fn (string $source): mixed => new HtmlReader()->read($source), [
            'nested' => [$this->repeat('<div class="ujo-wide">'), 5_000],
            'inline nested' => [$this->repeat('<b><i>', '', 'x'), 16_000],
            'footnotes' => [$this->repeat('<sup data-footnote="fn-1">1</sup>', '', '<aside id="footnote-fn-1">a</aside>'), 3_000],
            'images' => [$this->repeat('<p>a<img src="/a.png">b</p>'), 4_000],
            'lists' => [$this->repeat('<ul><li>a'), 11_000],
            'cells' => [$this->repeat('<tr>'.str_repeat('<td>x</td>', 50), '<table>', '</table>'), 220],
        ], static fn (string $source): mixed => HTMLDocument::createFromString($source, \LIBXML_NOERROR, 'UTF-8'));
    }

    /**
     * Tags nested deep, left open or closed without being opened, lists, tables, headings, footnotes, images, paragraphs
     * and containers by the thousand, and long attributes and options take a time that grows with their length.
     */
    public function testBBCodeIsReadInLinearTime(): void
    {
        $this->assertGrowsLinearly(static fn (string $source): mixed => new BBCodeReader(normalizer: self::normalizer())->read($source), [
            'nested' => [$this->repeat('[b][i]', '', 'x'), 16_000],
            'unmatched' => [static fn (int $units): string => str_repeat('[quote]', $units).str_repeat('[/s]', intdiv(12 * $units, 7)), 7_000],
            'brackets' => [$this->repeat('['), 100_000],
            'open options' => [$this->repeat('[url=a '), 14_000],
            'code' => [$this->repeat('[code]x[/code]'), 7_000],
            'items' => [$this->repeat('[*]a', '[list]'), 25_000],
            'nested lists' => [$this->repeat('[list][*]'), 11_000],
            'cells' => [$this->repeat('[tr]'.str_repeat('[td]x', 70), '[table]'), 340],
            'headings' => [$this->repeat('[h2]a[/h2]'), 10_000],
            'footnotes' => [$this->repeat('a[footnote]b[/footnote]'), 4_300],
            'images' => [$this->repeat('[img]https://e.x/a.png[/img][h1]x[/h1]'), 2_600],
            'paragraphs' => [$this->repeat("a\n\n"), 33_000],
            'a quote of paragraphs' => [$this->repeat("a\n\n", '[quote]'), 33_000],
            'containers' => [$this->repeat('[quote][note][wide][columns][column]'), 2_700],
            'attributes' => [$this->repeat('a=b ', '[img ', ']'), 25_000],
            'option' => [$this->repeat('a ', '[quote=', ']x[/quote]'), 50_000],
            'closing tags' => [$this->repeat('[/x', '', '[code]'), 33_000],
        ]);
    }

    /**
     * Reads each source at an eighth and at half of its full size, and compares the two times. Each case is the source
     * by its number of units, the number that makes 100 KB, and the growth it is held under when not the usual one;
     * `$beneath` is what the reading runs first, whose own growth the reading's may match.
     *
     * @param Closure(string): mixed                                           $read
     * @param array<string, array{0: Closure(int): string, 1: int, 2?: float}> $sources
     * @param (Closure(string): mixed)|null                                    $beneath
     */
    private function assertGrowsLinearly(Closure $read, array $sources, ?Closure $beneath = null): void
    {
        foreach ($sources as $shape => $case) {
            [$source, $units] = $case;
            self::assertGreaterThan(90_000, \strlen($source($units)), $shape);

            $small = $source(intdiv($units, 2 * self::GROWTH));
            $large = $source(intdiv($units, 2));
            $read($small);
            [$before, $after] = $this->fastest($read, $small, $large);
            $limit = $case[2] ?? self::MAXIMUM_RATIO;

            if ($beneath instanceof Closure) {
                [$parsedBefore, $parsedAfter] = $this->fastest($beneath, $small, $large);
                $limit = max($limit, 1.5 * $parsedAfter / max($parsedBefore, 1e-6));
            }

            self::assertLessThan($limit, $after / max($before, self::FLOOR), \sprintf('%s: %.3f s, then %.3f s for %d times as much text.', $shape, $before, $after, self::GROWTH));
        }
    }

    /**
     * The fastest of a few readings of each source, in seconds of processor time: the two are read in turn, so that a
     * busy spell of the machine slows both rather than one.
     *
     * @param Closure(string): mixed $read
     *
     * @return array{float, float}
     */
    private function fastest(Closure $read, string $small, string $large): array
    {
        $fastest = [\PHP_FLOAT_MAX, \PHP_FLOAT_MAX];

        for ($reading = 0; $reading < self::READINGS; ++$reading) {
            foreach ([$small, $large] as $index => $source) {
                $start = $this->processorTime();
                $read($source);
                $fastest[$index] = min($fastest[$index], $this->processorTime() - $start);
            }
        }

        return $fastest;
    }

    /**
     * The processor time the process has used, in seconds: unlike the time on the clock, it does not grow while the
     * other processes of a loaded machine take their turn.
     */
    private function processorTime(): float
    {
        $usage = getrusage();
        self::assertIsArray($usage);
        $seconds = 0.0;

        foreach (['ru_utime', 'ru_stime'] as $kind) {
            $whole = $usage[$kind.'.tv_sec'] ?? 0;
            $micro = $usage[$kind.'.tv_usec'] ?? 0;
            $seconds += (\is_int($whole) ? $whole : 0) + (\is_int($micro) ? $micro : 0) / 1e6;
        }

        return $seconds;
    }

    /**
     * A source made of a unit repeated, between a prefix and a suffix.
     *
     * @return Closure(int): string
     */
    private function repeat(string $unit, string $prefix = '', string $suffix = ''): Closure
    {
        return static fn (int $units): string => $prefix.str_repeat($unit, $units).$suffix;
    }
}
