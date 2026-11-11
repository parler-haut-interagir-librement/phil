<?php

declare(strict_types=1);

namespace Phil\Ujo;

use Phil\Ujo\Exception\InvalidDocument;
use Phil\Ujo\Exception\InvalidJson;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Json\JsonDecoder;
use Phil\Ujo\Json\JsonEncoder;
use Phil\Ujo\Text\PlainText;

/**
 * The library in one object: UJO JSON read and checked into a canonical document, written back, rendered as purified
 * HTML and converted to plain text. Each part can be replaced; the defaults read every type of the default registry.
 */
final readonly class Ujo
{
    private JsonDecoder $decoder;

    private JsonEncoder $encoder;

    private Normalizer $normalizer;

    private HtmlRenderer $renderer;

    private PlainText $text;

    public function __construct(
        ?Normalizer $normalizer = null,
        ?HtmlRenderer $renderer = null,
        ?PlainText $text = null,
        ?JsonDecoder $decoder = null,
    ) {
        $this->normalizer = $normalizer ?? new Normalizer();
        $this->renderer = $renderer ?? HtmlRenderer::default();
        $this->text = $text ?? new PlainText();
        $this->decoder = $decoder ?? new JsonDecoder();
        $this->encoder = new JsonEncoder();
    }

    /**
     * A document read from UJO JSON, checked at the level given — its shape by default — and in canonical form.
     *
     * @throws InvalidJson
     * @throws InvalidDocument
     * @throws UnknownBlockType
     */
    public function decode(string $json, Validation $level = Validation::Shape): Document
    {
        return $this->normalizer->normalize($this->decoder->decode($json), $level);
    }

    /**
     * A document checked at the level given — its shape by default — and put in canonical form.
     *
     * @throws InvalidDocument
     * @throws UnknownBlockType
     */
    public function normalize(Document $document, Validation $level = Validation::Shape): Document
    {
        return $this->normalizer->normalize($document, $level);
    }

    /**
     * The UJO JSON of a document, checked first.
     *
     * @throws InvalidDocument
     * @throws UnknownBlockType
     */
    public function encode(Document $document, bool $pretty = false): string
    {
        return $this->encoder->encode($this->normalizer->normalize($document), $pretty);
    }

    /**
     * The HTML of a document or of UJO JSON, checked first: what is rendered has passed the sanitizer.
     *
     * @throws InvalidJson
     * @throws InvalidDocument
     * @throws UnknownBlockType
     */
    public function toHtml(Document|string $document): string
    {
        return $this->renderer->render($this->read($document));
    }

    /**
     * The plain text of a document or of UJO JSON, checked first.
     *
     * @throws InvalidJson
     * @throws InvalidDocument
     * @throws UnknownBlockType
     */
    public function toText(Document|string $document): string
    {
        return $this->text->convert($this->read($document));
    }

    private function read(Document|string $document): Document
    {
        return \is_string($document) ? $this->decode($document) : $this->normalizer->normalize($document);
    }
}
