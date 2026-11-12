<?php

declare(strict_types=1);

namespace Phil\UxKit\Infrastructure\StructuredData;

use BackedEnum;
use Phil\StructuredData\Contract\NodeCollector;
use Phil\StructuredData\Graph;
use Phil\StructuredData\JsonLd;
use Phil\StructuredData\Node;
use Phil\StructuredData\Type\ImageObject;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;
use Symfony\Component\HttpFoundation\UrlHelper;

/**
 * An image of the page described to search engines as a schema.org `ImageObject`, from the props of the recipe: the
 * absolute address of its file, its width and height, its caption, its credit as `creditText`, the address of its
 * license, and how it was made as `digitalSourceType`. It joins the one graph of the page when an application gathers
 * one through `NodeCollector`; otherwise it is written in a `<script type="application/ld+json">` of its own, a data
 * block that a Content Security Policy does not govern, so it needs no nonce.
 *
 * An address is made absolute against the request by Symfony's `UrlHelper`, then read by `phil/url`: an inline image,
 * or an address `phil/url` refuses, is described by nothing.
 */
final readonly class ImageJsonLd
{
    /**
     * The members of schema.org's `IPTCDigitalSourceEnumeration`, by code of the IPTC vocabulary; the codes it has no
     * member for, `humanEdits` and the retired ones, say nothing.
     */
    private const array SOURCES = [
        'digitalCapture' => 'DigitalCaptureDigitalSource',
        'computationalCapture' => 'MultiFrameComputationalCaptureDigitalSource',
        'negativeFilm' => 'NegativeFilmDigitalSource',
        'positiveFilm' => 'PositiveFilmDigitalSource',
        'print' => 'PrintDigitalSource',
        'minorHumanEdits' => 'MinorHumanEditsDigitalSource',
        'compositeWithTrainedAlgorithmicMedia' => 'CompositeWithTrainedAlgorithmicMediaDigitalSource',
        'algorithmicallyEnhanced' => 'AlgorithmicallyEnhancedDigitalSource',
        'dataDrivenMedia' => 'DataDrivenMediaDigitalSource',
        'digitalArt' => 'DigitalArtDigitalSource',
        'trainedAlgorithmicMedia' => 'TrainedAlgorithmicMediaDigitalSource',
        'algorithmicMedia' => 'AlgorithmicMediaDigitalSource',
        'screenCapture' => 'ScreenCaptureDigitalSource',
        'virtualRecording' => 'VirtualRecordingDigitalSource',
        'composite' => 'CompositeDigitalSource',
        'compositeCapture' => 'CompositeCaptureDigitalSource',
        'compositeSynthetic' => 'CompositeSyntheticDigitalSource',
    ];

    /**
     * @param bool $allowLocalhost whether `localhost` and the names under `.localhost` are hosts, in development
     */
    public function __construct(
        private ?UrlHelper $urls = null,
        private bool $allowLocalhost = false,
        private ?NodeCollector $graph = null,
    ) {
    }

    /**
     * The node of an image, or null when its address is described by nothing.
     *
     * @param array<array-key, mixed> $image the props `src`, `width`, `height`, `caption`, `credit`, `licenseHref` and `creation`
     */
    public function node(array $image): ?Node
    {
        $src = $this->absolute($image['src'] ?? null);

        if (null === $src) {
            return null;
        }

        $creation = $image['creation'] ?? null;
        $creation = $creation instanceof BackedEnum ? $creation->value : $creation;

        $source = \is_string($creation) ? (self::SOURCES[$creation] ?? null) : null;

        return ImageObject::of(
            contentUrl: $src,
            caption: $this->text($image['caption'] ?? null),
            width: $this->size($image['width'] ?? null),
            height: $this->size($image['height'] ?? null),
            license: $this->absolute($image['licenseHref'] ?? null),
            creditText: $this->text($image['credit'] ?? null),
            properties: ['digitalSourceType' => null === $source ? null : 'https://schema.org/'.$source],
        );
    }

    /**
     * Nothing when the node joins the graph of the page, or the image has none; its script element otherwise.
     *
     * @param array<array-key, mixed> $image
     */
    public function script(array $image): string
    {
        $node = $this->node($image);

        if (!$node instanceof Node) {
            return '';
        }

        if ($this->graph instanceof NodeCollector) {
            $this->graph->add($node);

            return '';
        }

        return JsonLd::script(Graph::of($node));
    }

    /**
     * The absolute address of a file, or null for an inline image or an address `phil/url` refuses. Two slashes, or
     * backslashes, start the address of another host, read over `https` as the recipe reads it.
     */
    private function absolute(mixed $address): ?string
    {
        if (!\is_string($address)) {
            return null;
        }

        $address = trim(str_replace(["\t", "\n", "\r"], '', $address), "\x00..\x20");

        if (1 === preg_match('#^[/\\\\]{2}#', $address)) {
            $address = 'https://'.substr($address, 2);
        } elseif ('' !== $address && $this->urls instanceof UrlHelper) {
            $address = $this->urls->getAbsoluteUrl($address);
        }

        try {
            return Url::fromString($address, $this->allowLocalhost)->toString();
        } catch (UrlException) {
            return null;
        }
    }

    private function text(mixed $text): ?string
    {
        return \is_string($text) && '' !== trim($text) ? $text : null;
    }

    private function size(mixed $size): ?int
    {
        return match (true) {
            \is_int($size) => $size > 0 ? $size : null,
            \is_string($size) && 1 === preg_match('/^[1-9]\d*$/D', $size) => (int) $size,
            default => null,
        };
    }
}
