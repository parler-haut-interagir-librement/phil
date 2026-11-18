<?php

declare(strict_types=1);

namespace Phil\Markup\Tests\Unit\Reader;

use Closure;
use InvalidArgumentException;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Exception\InputTooLarge;
use Phil\Markup\Exception\MarkupException;
use Phil\Markup\Level;
use Phil\Markup\Markup;
use Phil\Markup\MarkupFormat;
use Phil\Markup\Reader\BBCode\BBCodeReader;
use Phil\Markup\Reader\BoundedReader;
use Phil\Markup\Reader\Contract\ReaderProvider;
use Phil\Markup\Reader\Creole\CreoleReader;
use Phil\Markup\Reader\DokuWiki\DokuWikiReader;
use Phil\Markup\Reader\Html\HtmlReader;
use Phil\Markup\Reader\Markdown\MarkdownReader;
use Phil\Markup\Reader\MarkupReader;
use Phil\Markup\Reader\MediaWiki\MediaWikiReader;
use Phil\Markup\Reader\Text\TextReader;
use Phil\Markup\Reader\UjoReader;
use Phil\Ujo\Json\JsonDecoder;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The ceiling of every entry point that takes a source: a source of the ceiling's length is read, one byte more is
 * refused whole, before it is parsed, by an `InputTooLarge` that says both lengths.
 */
#[CoversClass(InputTooLarge::class)]
#[CoversClass(BoundedReader::class)]
#[CoversClass(DefaultCompiler::class)]
#[CoversClass(Markup::class)]
#[CoversClass(MarkdownReader::class)]
#[CoversClass(BBCodeReader::class)]
#[CoversClass(MediaWikiReader::class)]
#[CoversClass(DokuWikiReader::class)]
#[CoversClass(CreoleReader::class)]
#[CoversClass(HtmlReader::class)]
#[CoversClass(TextReader::class)]
#[CoversClass(UjoReader::class)]
final class InputCeilingTest extends TestCase
{
    /**
     * Each reader, built with a ceiling, or without one when it is `null`.
     *
     * @return iterable<string, array{Closure(int<1, max>|null): MarkupReader}>
     */
    public static function readers(): iterable
    {
        yield 'markdown' => [static fn (?int $limit): MarkupReader => null === $limit ? new MarkdownReader() : new MarkdownReader(maxBytes: $limit)];
        yield 'bbcode' => [static fn (?int $limit): MarkupReader => null === $limit ? new BBCodeReader() : new BBCodeReader(maxBytes: $limit)];
        yield 'mediawiki' => [static fn (?int $limit): MarkupReader => null === $limit ? new MediaWikiReader() : new MediaWikiReader(maxBytes: $limit)];
        yield 'dokuwiki' => [static fn (?int $limit): MarkupReader => null === $limit ? new DokuWikiReader() : new DokuWikiReader(maxBytes: $limit)];
        yield 'creole' => [static fn (?int $limit): MarkupReader => null === $limit ? new CreoleReader() : new CreoleReader(maxBytes: $limit)];
        yield 'html' => [static fn (?int $limit): MarkupReader => null === $limit ? new HtmlReader() : new HtmlReader(maxBytes: $limit)];
        yield 'text' => [static fn (?int $limit): MarkupReader => null === $limit ? new TextReader() : new TextReader(maxBytes: $limit)];
        yield 'ujo' => [static fn (?int $limit): MarkupReader => null === $limit ? new UjoReader() : new UjoReader(maxBytes: $limit)];
    }

    /**
     * @param Closure(int<1, max>|null): MarkupReader $reader
     */
    #[DataProvider('readers')]
    public function testEachReaderReadsUpToItsCeilingAndRefusesMore(Closure $reader): void
    {
        $source = str_pad('{"blocks":[]}', 64, ' ');

        self::assertLessThanOrEqual(1, \count($reader(64)->read($source)->document->blocks));
        $this->assertRefused(65, 64, static fn (): mixed => $reader(64)->read($source.' '));
    }

    /**
     * The reader of each written format, with its ceiling by default: 1 MiB.
     *
     * @return iterable<string, array{Closure(int<1, max>|null): MarkupReader}>
     */
    public static function writtenReaders(): iterable
    {
        foreach (self::readers() as $name => $reader) {
            if ('ujo' !== $name) {
                yield $name => $reader;
            }
        }
    }

    /**
     * @param Closure(int<1, max>|null): MarkupReader $reader
     */
    #[DataProvider('writtenReaders')]
    public function testEachReaderOfAWrittenFormatRefusesASourceAboveOneMebibyteByDefault(Closure $reader): void
    {
        $this->assertRefused(1_048_577, 1_048_576, static fn (): mixed => $reader(null)->read(str_repeat(' ', 1_048_577)));
    }

    public function testTheDefaultCeilingIsOneMebibyte(): void
    {
        self::assertSame(1_048_576, MarkupReader::DEFAULT_MAX_BYTES);
    }

    /**
     * The reader of UJO takes the ceiling of the decoder of `phil/ujo`, 4 MiB: a source of that length is read, one
     * byte more refused before it is decoded.
     */
    public function testTheUjoReaderTakesTheCeilingOfTheDecoder(): void
    {
        self::assertSame(4_194_304, UjoReader::DEFAULT_MAX_BYTES);
        self::assertSame(JsonDecoder::DEFAULT_MAX_BYTES, UjoReader::DEFAULT_MAX_BYTES);
        self::assertSame([], new UjoReader()->read(str_pad('{"blocks":[]}', 4_194_304, ' '))->document->blocks);
        $this->assertRefused(4_194_305, 4_194_304, static fn (): mixed => new UjoReader()->read(str_pad('{"blocks":[]}', 4_194_305, ' ')));
    }

    /**
     * The compiler gives UJO its own ceiling, 4 MiB by default, and the other formats theirs; its reader of UJO and the
     * decoder behind it share the UJO ceiling, whichever is the larger.
     */
    public function testTheCompilerHasACeilingOfItsOwnForUjo(): void
    {
        $ujo = str_pad('{"blocks":[]}', 4_194_304, ' ');
        self::assertSame('', new DefaultCompiler()->compile($ujo, MarkupFormat::Ujo));
        self::assertSame([], new DefaultCompiler()->reader(MarkupFormat::Ujo)->read($ujo)->document->blocks);
        $this->assertRefused(4_194_305, 4_194_304, static fn (): mixed => new DefaultCompiler()->compile($ujo.' ', 'ujo'));
        $this->assertRefused(4_194_305, 4_194_304, static fn (): mixed => new DefaultCompiler()->reader('ujo')->read($ujo.' '));
        $this->assertRefused(1_048_577, 1_048_576, static fn (): mixed => new DefaultCompiler()->compile(str_repeat(' ', 1_048_577), 'markdown'));

        $compiler = new DefaultCompiler(maxBytes: 8, ujoMaxBytes: 16);
        $source = str_pad('{"blocks":[]}', 16, ' ');
        self::assertSame('', $compiler->compile($source, 'ujo'));
        self::assertSame('', $compiler->compileFor($source, MarkupFormat::Ujo, Level::Basic));
        self::assertSame([], $compiler->reader('ujo')->read($source)->document->blocks);
        $this->assertRefused(17, 16, static fn (): mixed => $compiler->compile($source.' ', 'ujo'));
        $this->assertRefused(17, 16, static fn (): mixed => $compiler->reader(MarkupFormat::Ujo)->read($source.' '));
        $this->assertRefused(9, 8, static fn (): mixed => $compiler->compile('123456789', 'html'));

        // Above the decoder's own 4 MiB, the decoder the compiler builds takes the UJO ceiling as well.
        $large = str_pad('{"blocks":[]}', 4_194_305, ' ');
        self::assertSame([], new DefaultCompiler(ujoMaxBytes: 4_194_305)->reader('ujo')->read($large)->document->blocks);
    }

    /**
     * The ceiling counts bytes: a character of two bytes counts twice.
     */
    public function testTheCeilingCountsBytesNotCharacters(): void
    {
        self::assertSame('paragraph', new TextReader(maxBytes: 4)->read('éé')->document->blocks[0]->type);
        $this->assertRefused(6, 4, static fn (): mixed => new TextReader(maxBytes: 4)->read('ééé'));
    }

    public function testTheExceptionSaysBothLengths(): void
    {
        $exception = InputTooLarge::of(12, 10);

        self::assertSame('The input is 12 bytes long, above the limit of 10 bytes.', $exception->getMessage());
        self::assertSame([12, 10], [$exception->bytes, $exception->limit]);
        self::assertInstanceOf(MarkupException::class, $exception);
        self::assertInstanceOf(InvalidArgumentException::class, $exception);

        InputTooLarge::check('1234567890', 10);
        $this->assertRefused(11, 10, static function (): null {
            InputTooLarge::check('12345678901', 10);

            return null;
        });
    }

    /**
     * The compiler refuses a source above its ceiling in every format — HTML and plain text, which it compiles without a
     * reader, included — and gives readers that have its ceiling.
     */
    public function testTheCompilerRefusesASourceAboveItsCeilingInEveryFormat(): void
    {
        $compiler = new DefaultCompiler(maxBytes: 8, ujoMaxBytes: 8);

        self::assertSame('<b>1</b>', $compiler->compile('<b>1</b>', 'html'));
        self::assertSame('<p>12345678</p>', $compiler->compile('12345678', 'text'));

        foreach (MarkupFormat::values() as $format) {
            $this->assertRefused(9, 8, static fn (): mixed => $compiler->compile('123456789', $format));
            $this->assertRefused(9, 8, static fn (): mixed => $compiler->compileFor('123456789', $format, Level::All));
            $this->assertRefused(9, 8, static fn (): mixed => $compiler->reader($format)->read('123456789'));
        }

        self::assertSame('paragraph', $compiler->reader(MarkupFormat::Markdown, Level::Basic)->read('12345678')->document->blocks[0]->type);
        $this->assertRefused(1_048_577, 1_048_576, static fn (): mixed => new DefaultCompiler()->compile(str_repeat('a', 1_048_577), 'html'));
        self::assertSame(1_048_576, \strlen(new DefaultCompiler()->compile(str_repeat('a', 1_048_576), 'html')));
    }

    /**
     * The reader of an add-on's format is put behind the compiler's ceiling, whatever its own.
     */
    public function testTheReaderOfAnAddOnIsPutBehindTheCompilersCeiling(): void
    {
        $provider = new class implements ReaderProvider {
            public function reader(Level $level, Normalizer $normalizer): MarkupReader
            {
                return new TextReader($normalizer, \PHP_INT_MAX);
            }
        };
        $compiler = new DefaultCompiler(readers: ['plain' => $provider], maxBytes: 8);

        self::assertSame('<p class="ujo-paragraph">12345678</p>', $compiler->compile('12345678', 'plain'));
        self::assertSame('paragraph', $compiler->reader('plain')->read('12345678')->document->blocks[0]->type);
        $this->assertRefused(9, 8, static fn (): mixed => $compiler->reader('plain')->read('123456789'));
        $this->assertRefused(9, 8, static fn (): mixed => $compiler->compile('123456789', 'plain'));
        $this->assertRefused(9, 8, static fn (): mixed => new BoundedReader(new TextReader(), 8)->read('123456789'));
    }

    public function testTheFacadeRefusesWhatItsCompilerRefuses(): void
    {
        $markup = new Markup(new DefaultCompiler(maxBytes: 8), SanitizerPurifier::defaultPolicies());

        self::assertSame('<p>12345678</p>', $markup->toHtml('12345678', 'text'));
        $this->assertRefused(9, 8, static fn (): mixed => $markup->toHtml('123456789', 'markdown'));
        $this->assertRefused(9, 8, static fn (): mixed => $markup->text('123456789', 'html'));
    }

    /**
     * A UJO source is refused by the ceiling of the reader before the decoder of `phil/ujo`, and its own, sees it.
     */
    public function testAUjoSourceIsRefusedBeforeItIsDecoded(): void
    {
        $this->assertRefused(9, 8, static fn (): mixed => new UjoReader(new Ujo(), 8)->read('not json!'));
    }

    /**
     * @param Closure(): mixed $read
     */
    private function assertRefused(int $bytes, int $limit, Closure $read): void
    {
        try {
            $read();
            self::fail(\sprintf('A source of %d bytes should be refused above %d.', $bytes, $limit));
        } catch (InputTooLarge $inputTooLarge) {
            self::assertSame([$bytes, $limit], [$inputTooLarge->bytes, $inputTooLarge->limit]);
        }
    }
}
