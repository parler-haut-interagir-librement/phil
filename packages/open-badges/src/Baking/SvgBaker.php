<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Baking;

use Dom\DocumentType;
use Dom\Element;
use Dom\XMLDocument;
use DOMException;
use Phil\OpenBadges\Exception\InvalidImage;
use ValueError;

/**
 * Bakes a credential into an SVG image, and reads it back: an `openbadges:credential` element, in the namespace
 * `https://purl.imsglobal.org/ob/v3p0`, first child of the root — a JSON Web Token in its `verify` attribute, the JSON
 * of a document with a Data Integrity proof as its character data, split in two sections where it holds `]]>`. The
 * image keeps a single one: baking again replaces it.
 *
 * The image is parsed without the network; a declaration of entities, an internal subset of its document type, or an
 * image larger than `MAX_BYTES` is refused.
 */
final class SvgBaker
{
    public const string CREDENTIAL_NAMESPACE = 'https://purl.imsglobal.org/ob/v3p0';

    public const int MAX_BYTES = 4_194_304;

    private const string SVG = 'http://www.w3.org/2000/svg';

    private function __construct()
    {
    }

    /**
     * The image with the credential baked in.
     *
     * @param array<string, mixed>|string $credential a secured document, or a compact JSON Web Token
     *
     * @throws InvalidImage
     */
    public static function bake(string $svg, array|string $credential): string
    {
        $text = Baked::text($credential);
        [$document, $root] = self::load($svg);

        foreach (self::credentials($document) as $old) {
            $old->remove();
        }

        $element = $document->createElementNS(self::CREDENTIAL_NAMESPACE, 'openbadges:credential');

        if (Baked::isJwt($text)) {
            $element->setAttribute('verify', $text);
        } else {
            $pieces = explode(']]>', $text);
            $last = \count($pieces) - 1;

            foreach ($pieces as $index => $piece) {
                $element->append($document->createCDATASection((0 === $index ? '' : '>').$piece.($index === $last ? '' : ']]')));
            }
        }

        $root->prepend($element);

        return (string) $document->saveXml();
    }

    /**
     * The credential baked into the image, or null when it holds none.
     *
     * @throws InvalidImage
     */
    public static function extract(string $svg): ?string
    {
        $credentials = self::credentials(self::load($svg)[0]);

        if (\count($credentials) > 1) {
            throw InvalidImage::because('The image holds more than one baked credential.');
        }

        if ([] === $credentials) {
            return null;
        }

        $verify = (string) $credentials[0]->getAttribute('verify');

        return Baked::check('' === $verify ? trim((string) $credentials[0]->textContent) : $verify);
    }

    /**
     * @return list<Element>
     */
    private static function credentials(XMLDocument $document): array
    {
        $found = [];

        foreach ($document->getElementsByTagNameNS(self::CREDENTIAL_NAMESPACE, 'credential') as $element) {
            $found[] = $element;
        }

        return $found;
    }

    /**
     * @return array{XMLDocument, Element}
     */
    private static function load(string $svg): array
    {
        if (\strlen($svg) > self::MAX_BYTES) {
            throw InvalidImage::because(\sprintf('An SVG image is at most %d bytes.', self::MAX_BYTES));
        }

        // A NUL makes the parser guess another encoding, and fail on it with a warning: the image is UTF-8.
        if (str_contains($svg, "\0") || !mb_check_encoding($svg, 'UTF-8')) {
            throw InvalidImage::because('The image is not well-formed XML.');
        }

        if (false !== stripos($svg, '<!ENTITY')) {
            throw InvalidImage::because('An SVG image declares no entities.');
        }

        try {
            $document = XMLDocument::createFromString($svg, \LIBXML_NONET | \LIBXML_NOERROR | \LIBXML_NOWARNING);
        } catch (DOMException|ValueError) {
            throw InvalidImage::because('The image is not well-formed XML.');
        }

        if ($document->doctype instanceof DocumentType && '' !== (string) $document->doctype->internalSubset) {
            throw InvalidImage::because('An SVG image declares no entities.');
        }

        $root = $document->documentElement;

        if (!$root instanceof Element || 'svg' !== $root->localName || self::SVG !== $root->namespaceURI) {
            throw InvalidImage::because("The image's root is an svg element of the SVG namespace.");
        }

        return [$document, $root];
    }
}
