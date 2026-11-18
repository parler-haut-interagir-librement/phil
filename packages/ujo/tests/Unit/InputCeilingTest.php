<?php

declare(strict_types=1);

namespace Phil\Ujo\Tests\Unit;

use Closure;
use InvalidArgumentException;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\InputTooLarge;
use Phil\Ujo\Exception\UjoException;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Html\InlineProfile;
use Phil\Ujo\Html\InlineSanitizer;
use Phil\Ujo\Json\JsonDecoder;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Text\PlainText;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The ceiling of the inline sanitizer, through which every path reads inline HTML — the normalizer, the renderer, the
 * conversion to plain text —: a fragment of the ceiling's length is read, one byte more is refused whole, before it is
 * read, by an `InputTooLarge` that says both lengths.
 */
#[CoversClass(InputTooLarge::class)]
#[CoversClass(InlineSanitizer::class)]
#[CoversClass(PlainText::class)]
#[CoversClass(Ujo::class)]
final class InputCeilingTest extends TestCase
{
    public function testTheSanitizerReadsUpToItsCeilingAndRefusesMore(): void
    {
        $sanitizer = new InlineSanitizer(maxBytes: 9);

        self::assertSame('<b>a</b>b', $sanitizer->sanitize('<b>a</b>b'));
        self::assertSame('ab', $sanitizer->toText('<b>a</b>b'));
        $this->assertRefused(10, 9, static fn (): mixed => $sanitizer->sanitize('<b>a</b>bc'));
        $this->assertRefused(10, 9, static fn (): mixed => $sanitizer->toText('<b>a</b>bc'));
        $this->assertRefused(10, 9, static fn (): mixed => $sanitizer->withProfile('mine', InlineProfile::plain())->sanitize('0123456789', 'mine'));
        self::assertSame('012345678', $sanitizer->withProfile('mine', InlineProfile::plain())->sanitize('012345678', 'mine'));
    }

    /**
     * The default ceiling is that of the JSON decoder: no fragment of a document it reads is refused.
     */
    public function testTheDefaultCeilingIsThatOfTheDecoder(): void
    {
        self::assertSame(4_194_304, InlineSanitizer::DEFAULT_MAX_BYTES);
        self::assertSame(JsonDecoder::DEFAULT_MAX_BYTES, InlineSanitizer::DEFAULT_MAX_BYTES);

        $sanitizer = new InlineSanitizer();
        self::assertSame(4_194_304, \strlen($sanitizer->sanitize(str_repeat('a', 4_194_304))));
        $this->assertRefused(4_194_305, 4_194_304, static fn (): mixed => $sanitizer->sanitize(str_repeat('<b>', 1_398_101).'ab'));
        $this->assertRefused(4_194_305, 4_194_304, static fn (): mixed => $sanitizer->toText(str_repeat('a', 4_194_305)));
    }

    /**
     * A document built in PHP, never decoded, is converted to text through the sanitizer, and its ceiling: the plain
     * text of each field, and of each field of a type converted by a conversion of its own.
     */
    public function testThePlainTextOfADocumentHasTheCeilingOfItsSanitizer(): void
    {
        $text = new PlainText(sanitizer: new InlineSanitizer(maxBytes: 9));
        $short = new Document([new Block('a', 'paragraph', ['text' => '<b>a</b>b'])]);
        $long = new Document([new Block('a', 'paragraph', ['text' => '<b>a</b>bc'])]);

        self::assertSame('ab', $text->convert($short));
        $this->assertRefused(10, 9, static fn (): mixed => $text->convert($long));
        $this->assertRefused(10, 9, static fn (): mixed => $text->with('note', static fn (): string => '')->convert($long));
        $this->assertRefused(4_194_305, 4_194_304, static fn (): mixed => new PlainText()->convert(new Document([new Block('a', 'paragraph', ['text' => str_repeat('a', 4_194_305)])])));
    }

    /**
     * The renderer and the normalizer read inline HTML through the sanitizer they are given, and its ceiling.
     */
    public function testTheRendererAndTheNormalizerHaveTheCeilingOfTheirSanitizer(): void
    {
        $sanitizer = new InlineSanitizer(maxBytes: 9);
        $long = new Document([new Block('a', 'paragraph', ['text' => '<b>a</b>bc'])]);

        self::assertSame('<p class="ujo-paragraph">0</p>', HtmlRenderer::default($sanitizer)->render(new Document([new Block('a', 'paragraph', ['text' => '0'])])));
        $this->assertRefused(10, 9, static fn (): mixed => HtmlRenderer::default($sanitizer)->render($long));
        $this->assertRefused(10, 9, static fn (): mixed => new Ujo(new Normalizer(sanitizer: $sanitizer))->normalize($long));
        $this->assertRefused(10, 9, static fn (): mixed => new Ujo(text: new PlainText(sanitizer: $sanitizer))->toText($long));
    }

    /**
     * The ceiling counts bytes: a character of two bytes counts twice.
     */
    public function testTheCeilingCountsBytes(): void
    {
        self::assertSame('éé', new InlineSanitizer(maxBytes: 4)->toText('éé'));
        $this->assertRefused(6, 4, static fn (): mixed => new InlineSanitizer(maxBytes: 4)->toText('ééé'));
    }

    public function testTheExceptionSaysBothLengths(): void
    {
        $exception = InputTooLarge::of(12, 10);

        self::assertSame('The input is 12 bytes long, above the limit of 10 bytes.', $exception->getMessage());
        self::assertSame([12, 10], [$exception->bytes, $exception->limit]);
        self::assertInstanceOf(UjoException::class, $exception);
        self::assertInstanceOf(InvalidArgumentException::class, $exception);

        InputTooLarge::check('1234567890', 10);
        $this->assertRefused(11, 10, static function (): null {
            InputTooLarge::check('12345678901', 10);

            return null;
        });
    }

    /**
     * @param Closure(): mixed $call
     */
    private function assertRefused(int $bytes, int $limit, Closure $call): void
    {
        try {
            $call();
            self::fail(\sprintf('A fragment of %d bytes should be refused above %d.', $bytes, $limit));
        } catch (InputTooLarge $inputTooLarge) {
            self::assertSame([$bytes, $limit], [$inputTooLarge->bytes, $inputTooLarge->limit]);
        }
    }
}
