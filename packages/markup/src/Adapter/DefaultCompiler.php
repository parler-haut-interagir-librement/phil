<?php

declare(strict_types=1);

namespace Phil\Markup\Adapter;

use Override;
use Phil\Markup\Exception\InputTooLarge;
use Phil\Markup\Exception\InvalidSource;
use Phil\Markup\Exception\UnknownFormat;
use Phil\Markup\Level;
use Phil\Markup\LevelAwareCompiler;
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
use Phil\Markup\Reader\RawNormalizer;
use Phil\Markup\Reader\Text\TextReader;
use Phil\Markup\Reader\UjoReader;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Json\JsonDecoder;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Ujo;

/**
 * Every format. Markdown, BBCode, MediaWiki, DokuWiki, Creole and UJO are read into a UJO document, which `phil/ujo`
 * renders as HTML; raw HTML written in them is kept at the level `all` and escaped below it. The checkbox of a
 * checklist item is kept at `all`, and written as text below it, `☑` or `☐` (`CheckboxText`). HTML is taken as written
 * and plain text by this library's own compiler, their readers only converting them into UJO on demand. The formats of
 * add-ons are read by the readers their `ReaderProvider` gives, by name, then rendered the same way.
 *
 * A source longer than `maxBytes` is refused before it is read, whatever its format, HTML and plain text included: the
 * readers the compiler gives have the same ceiling, those of the add-ons behind it. A UJO source has its own ceiling,
 * `ujoMaxBytes`, which its reader and the decoder of `phil/ujo` behind it share.
 */
final readonly class DefaultCompiler implements LevelAwareCompiler
{
    /** What names a format of an add-on: a lower-case letter, then up to 15 lower-case letters, digits or hyphens. */
    private const string NAME = '/^[a-z][a-z0-9-]{0,15}$/D';

    private HtmlRenderer $renderer;

    /** The renderer below the level `all`, the checkboxes of checklists written as text. */
    private HtmlRenderer $textRenderer;

    private Normalizer $normalizer;

    /** @var array<string, ReaderProvider> */
    private array $readers;

    /**
     * @param iterable<string, ReaderProvider> $readers     the readers of the add-ons' formats, by format name
     * @param int<1, max>                      $maxBytes    the longest source compiled or read, in bytes, but in UJO; a longer
     *                                                      one is refused
     * @param int<1, max>                      $ujoMaxBytes the longest UJO source compiled or read, in bytes; a longer one is
     *                                                      refused
     *
     * @throws UnknownFormat when a name is already a format, or not a name
     */
    public function __construct(
        ?HtmlRenderer $renderer = null,
        iterable $readers = [],
        private int $maxBytes = MarkupReader::DEFAULT_MAX_BYTES,
        private int $ujoMaxBytes = UjoReader::DEFAULT_MAX_BYTES,
    ) {
        $this->renderer = $renderer ?? HtmlRenderer::default();
        $this->textRenderer = $this->renderer->withFilter(new CheckboxText());
        $this->normalizer = RawNormalizer::get();
        $added = [];

        foreach ($readers as $name => $provider) {
            $added[$name] = 1 === preg_match(self::NAME, $name) && null === MarkupFormat::tryFrom($name) ? $provider : throw UnknownFormat::taken($name);
        }

        $this->readers = $added;
    }

    /**
     * The name of every format this compiler knows: the built-in ones, then the add-ons', in the order given.
     *
     * @return list<string>
     */
    public function formats(): array
    {
        return [...MarkupFormat::values(), ...array_keys($this->readers)];
    }

    /**
     * The HTML of a source whose level is not known: raw HTML in the written formats is escaped, as below `all`.
     *
     * @throws InputTooLarge when the source is longer than the compiler's ceiling
     * @throws InvalidSource when a UJO source is not valid
     * @throws UnknownFormat when no format has that name
     */
    #[Override]
    public function compile(string $source, MarkupFormat|string $format): string
    {
        return $this->compileFor($source, $format, Level::Forum);
    }

    /**
     * @throws InputTooLarge when the source is longer than the compiler's ceiling
     * @throws InvalidSource when a UJO source is not valid
     * @throws UnknownFormat when no format has that name
     */
    #[Override]
    public function compileFor(string $source, MarkupFormat|string $format, Level $level): string
    {
        $known = MarkupFormat::tryFrom(MarkupFormat::nameOf($format));
        InputTooLarge::check($source, MarkupFormat::Ujo === $known ? $this->ujoMaxBytes : $this->maxBytes);

        return match ($known) {
            MarkupFormat::Html => $source,
            MarkupFormat::Text => PlainText::compile($source),
            default => (Level::All === $level ? $this->renderer : $this->textRenderer)->render($this->reader($format, $level)->read($source)->document),
        };
    }

    /**
     * The reader of a format into UJO, for a source read at a level. HTML, compiled as written, and plain text, compiled
     * by `PlainText`, have a reader all the same, to convert them into UJO. Each refuses a source longer than the
     * compiler's ceiling, the reader of UJO longer than its UJO ceiling.
     *
     * @throws UnknownFormat when no format has that name
     */
    public function reader(MarkupFormat|string $format, Level $level = Level::All): MarkupReader
    {
        $name = MarkupFormat::nameOf($format);

        return match (MarkupFormat::tryFrom($name)) {
            MarkupFormat::Markdown => new MarkdownReader(Level::All === $level, $this->normalizer, maxBytes: $this->maxBytes),
            MarkupFormat::BBCode => new BBCodeReader(Level::All === $level, $this->normalizer, $this->maxBytes),
            MarkupFormat::MediaWiki => new MediaWikiReader(Level::All === $level, $this->normalizer, maxBytes: $this->maxBytes),
            MarkupFormat::DokuWiki => new DokuWikiReader(Level::All === $level, $this->normalizer, maxBytes: $this->maxBytes),
            MarkupFormat::Creole => new CreoleReader(Level::All === $level, $this->normalizer, maxBytes: $this->maxBytes),
            MarkupFormat::Html => new HtmlReader($this->normalizer, maxBytes: $this->maxBytes),
            MarkupFormat::Ujo => new UjoReader(new Ujo($this->normalizer, decoder: new JsonDecoder($this->ujoMaxBytes)), $this->ujoMaxBytes),
            MarkupFormat::Text => new TextReader($this->normalizer, $this->maxBytes),
            null => new BoundedReader(($this->readers[$name] ?? throw UnknownFormat::named($name, $this->formats()))->reader($level, $this->normalizer), $this->maxBytes),
        };
    }
}
