<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Markup;

use DateTimeImmutable;
use Override;
use Phil\ContentBundle\Application\Contract\ConversionLosses;
use Phil\ContentBundle\Application\Contract\RawHtmlPermission;
use Phil\ContentBundle\Application\Publication\BodyConverter;
use Phil\ContentBundle\Application\Publication\ConversionLoss;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\ValueObject\Body;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Exception\InvalidSource;
use Phil\Markup\HtmlPurifier;
use Phil\Markup\Level;
use Phil\Markup\MarkupFormat;
use Phil\Markup\Reader\Loss;
use Phil\Markup\Reader\Reading;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Json\JsonEncoder;

/**
 * The `BodyConverter` of the bundle, on `phil/markup`'s readers: the source is read into a UJO document by the reader
 * of its format, the document is dated with the moment of the publication and written as JSON, and that JSON is
 * compiled and purified at `Level::All`, as the data mapper purifies a draft.
 *
 * - **The source is read at `Level::Forum`**, the level `DefaultCompiler::compile()` reads at, so the published text
 *   says what its draft showed: raw HTML written in Markdown, which the draft escaped, is text in the UJO too, never a
 *   `raw` block, and a `raw` block written in a UJO source becomes a paragraph showing its HTML as text. HTML and
 *   plain text, which the draft compiles as written, are converted by their readers into blocks.
 * - **A publisher granted the `RawHtmlPermission` publishes raw HTML**: the source is read at `Level::All`, so the raw
 *   HTML of the written formats and the `raw` blocks of UJO stay `raw` blocks, their HTML kept as `phil/ujo`'s `raw`
 *   profile allows, as the draft of that publisher showed it. Without a `RawHtmlPermission`, no publisher is.
 * - **The document's `time` is the moment of the publication**, in milliseconds since the Unix epoch; the time a
 *   UJO source held is replaced.
 * - **What the reading does not keep is told before publishing**: `of()` reads the source as `toUjo()` does and
 *   gives the losses the reader listed.
 */
final readonly class UjoBodyConverter implements BodyConverter, ConversionLosses
{
    public const Level LEVEL = Level::Forum;

    public function __construct(
        private DefaultCompiler $compiler,
        private HtmlPurifier $purifier,
        private JsonEncoder $encoder = new JsonEncoder(),
        private ?RawHtmlPermission $rawHtml = null,
    ) {
    }

    #[Override]
    public function toUjo(Body $body, DateTimeImmutable $publishedAt): Body
    {
        $rawHtml = $this->rawHtml?->isGranted() ?? false;

        $document = $this->read($body->source, $body->format)->document;

        $blocks = $rawHtml ? $document->blocks : array_map(self::rawAsText(...), $document->blocks);
        $json = $this->encoder->encode(new Document($blocks, (int) $publishedAt->format('Uv'), $document->version));
        $html = $this->compiler->compileFor($json, MarkupFormat::Ujo, Level::All);

        return Body::of($json, MarkupFormat::Ujo, $this->purifier->purify($html, Level::All));
    }

    #[Override]
    public function of(string $source, MarkupFormat|string $format): array
    {
        return array_map(static fn (Loss $loss): ConversionLoss => new ConversionLoss($loss->element, $loss->path, $loss->reason->value), $this->read($source, $format)->losses);
    }

    /**
     * The source read at the level of its publisher.
     *
     * @throws InvalidContent
     */
    private function read(string $source, MarkupFormat|string $format): Reading
    {
        try {
            return $this->compiler->reader($format, ($this->rawHtml?->isGranted() ?? false) ? Level::All : self::LEVEL)->read($source);
        } catch (InvalidSource $invalidSource) {
            throw InvalidContent::because($invalidSource->getMessage());
        }
    }

    /**
     * The block with every `raw` block, itself or nested in it, replaced by a paragraph that shows its HTML as text.
     */
    private static function rawAsText(Block $block): Block
    {
        if ('raw' !== $block->type) {
            return $block->withData(self::nestedRawAsText($block->data));
        }

        // The normalizer has checked the block: its `html` is a text.
        $html = $block->data['html'];

        return new Block($block->id, 'paragraph', ['text' => htmlspecialchars(\is_string($html) ? $html : '', \ENT_QUOTES)], $block->tunes);
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private static function nestedRawAsText(array $data): array
    {
        return array_map(static fn (mixed $value): mixed => match (true) {
            $value instanceof Block => self::rawAsText($value),
            \is_array($value) => self::nestedRawAsText($value),
            default => $value,
        }, $data);
    }
}
