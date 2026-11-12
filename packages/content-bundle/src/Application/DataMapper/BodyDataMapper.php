<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\DataMapper;

use Override;
use Phil\ContentBundle\Application\Contract\RawHtmlPermission;
use Phil\ContentBundle\Application\Pipeline\DataMapper;
use Phil\ContentBundle\Domain\Capability\BodyCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\ValueObject\Body;
use Phil\Markup\Exception\InvalidSource;
use Phil\Markup\Exception\UnknownFormat;
use Phil\Markup\HtmlPurifier;
use Phil\Markup\Level;
use Phil\Markup\LevelAwareCompiler;
use Phil\Markup\MarkupCompiler;
use Phil\Markup\MarkupFormat;

/**
 * Writes `BodyCapable` on the localized row: compiles `bodySource` in `bodyFormat`, purifies the HTML, and
 * stores the three. **A draft keeps the format its author writes in**; publishing converts it into UJO
 * (`BodyConverter`).
 *
 * - `bodySource` absent leaves the body alone; `null` or blank clears it. The source is kept as written,
 *   leading spaces included.
 * - `bodyFormat` is the name of a format the compiler knows — a value of `MarkupFormat`, `markdown`, `html`,
 *   `bbcode`, `mediawiki`, `dokuwiki`, `creole`, `text` or `ujo`, or a format an add-on brings —, and `markdown` when
 *   absent. A format the compiler does not know, and a UJO source that is not a valid document, are refused.
 * - **An editor granted the `RawHtmlPermission` sees their raw HTML rendered**: with a `LevelAwareCompiler`, the
 *   source is compiled for `Level::All`, so the raw HTML written in Markdown or another written format becomes `raw`
 *   blocks, kept as `phil/ujo`'s `raw` profile allows and purified, as their publication will show it. Every other
 *   editor sees it as text, as `MarkupCompiler::compile()` reads it.
 */
final readonly class BodyDataMapper implements DataMapper
{
    public function __construct(
        private MarkupCompiler $compiler,
        private HtmlPurifier $purifier,
        private ?RawHtmlPermission $rawHtml = null,
    ) {
    }

    #[Override]
    public function map(DimensionContent $unlocalized, DimensionContent $localized, array $data): void
    {
        if (!$localized instanceof BodyCapable || !SubmittedData::has($data, 'bodySource')) {
            return;
        }

        $source = SubmittedData::untrimmedString($data, 'bodySource');

        if (null === $source) {
            $localized->setBody(null);

            return;
        }

        $format = SubmittedData::string($data, 'bodyFormat') ?? MarkupFormat::Markdown->value;

        try {
            $html = $this->purifier->purify($this->compile($source, $format), Level::All);
        } catch (UnknownFormat $unknownFormat) {
            throw InvalidContent::because(\sprintf('"%s" is not a body format; use one of: %s.', $format, implode(', ', $unknownFormat->known)));
        } catch (InvalidSource $invalidSource) {
            throw InvalidContent::because($invalidSource->getMessage());
        }

        $localized->setBody(Body::of($source, $format, $html));
    }

    /**
     * @throws InvalidSource
     * @throws UnknownFormat
     */
    private function compile(string $source, string $format): string
    {
        if ($this->compiler instanceof LevelAwareCompiler && ($this->rawHtml?->isGranted() ?? false)) {
            return $this->compiler->compileFor($source, $format, Level::All);
        }

        return $this->compiler->compile($source, $format);
    }
}
