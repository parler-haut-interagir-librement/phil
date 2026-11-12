<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tmx;

use DOMElement;
use DOMNode;
use DOMText;
use Phil\TranslationMemory\Exception\InvalidLanguage;
use Phil\TranslationMemory\Exception\InvalidTmx;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Memory\MemoryUnit;
use Phil\TranslationMemory\Text;
use Phil\TranslationMemory\Xliff\InlineCodes;
use Phil\TranslationMemory\Xml;

/**
 * Reads a TMX file — 1.4b, and 1.1 to 1.3 as far as they agree — into translation units.
 *
 * A file that declares a document type is refused before it is parsed, and nothing is fetched from the network. The
 * source of each `<tu>` is the `<tuv>` of its `srclang`, or of the header's; with `*all*`, the first `<tuv>`. Every
 * other `<tuv>` gives a unit from that source into its language. Inline codes come back as the HTML they hold —
 * `<bpt>`, `<ept>`, `<ph>` and `<it>` —, `<hi>` and `<sub>` keep their words, and text is escaped back as inline HTML.
 * A `<tuv>` without words, or a `<tu>` without its source, is left out; a `x-origin` property is the unit's origin.
 */
final readonly class TmxReader
{
    /** The most `<tu>` elements a file holds. */
    public const int MAX_UNITS = 100_000;

    /**
     * @return list<MemoryUnit>
     *
     * @throws InvalidTmx when the text is not well-formed XML, declares a document type, is not TMX with a source
     *                    language, holds a language tag that is not one, or more than 100 000 units
     */
    public function read(string $xml): array
    {
        $root = Xml::root($xml);

        if (!$root instanceof DOMElement || 'tmx' !== $root->localName) {
            throw InvalidTmx::because('the text is not well-formed XML with a <tmx> root and no document type.');
        }

        $header = Xml::children($root, 'header')[0] ?? null;
        $body = Xml::children($root, 'body')[0] ?? null;

        if (!$header instanceof DOMElement || !$body instanceof DOMElement) {
            throw InvalidTmx::because('a TMX file holds a <header> and a <body>.');
        }

        $tus = Xml::children($body, 'tu');

        if (\count($tus) > self::MAX_UNITS) {
            throw InvalidTmx::because(\sprintf('a file holds at most %d units.', self::MAX_UNITS));
        }

        try {
            $units = [];

            foreach ($tus as $tu) {
                array_push($units, ...$this->unit($tu, '' === $tu->getAttribute('srclang') ? $header->getAttribute('srclang') : $tu->getAttribute('srclang')));
            }

            return $units;
        } catch (InvalidLanguage $invalidLanguage) {
            throw InvalidTmx::because($invalidLanguage->getMessage());
        }
    }

    /**
     * @return list<MemoryUnit>
     */
    private function unit(DOMElement $tu, string $sourceLanguage): array
    {
        $variants = [];

        foreach (Xml::children($tu, 'tuv') as $tuv) {
            $seg = Xml::children($tuv, 'seg')[0] ?? null;
            $variants[] = [LanguageTag::of(Xml::language($tuv)), $seg instanceof DOMElement ? $this->content($seg) : ''];
        }

        $source = null;

        foreach ($variants as $index => [$language]) {
            if ('*all*' === $sourceLanguage ? 0 === $index : $language->equals(LanguageTag::of($sourceLanguage))) {
                $source = $index;

                break;
            }
        }

        if (null === $source || !Text::hasWords($variants[$source][1])) {
            return [];
        }

        $origin = null;

        foreach (Xml::children($tu, 'prop') as $prop) {
            $origin = TmxWriter::ORIGIN === $prop->getAttribute('type') ? $prop->textContent : $origin;
        }

        [$sourceTag, $sourceText] = $variants[$source];
        $units = [];

        foreach ($variants as $index => [$language, $text]) {
            if ($index !== $source && Text::hasWords($text)) {
                $units[] = new MemoryUnit($sourceTag, $language, $sourceText, $text, $origin);
            }
        }

        return $units;
    }

    private function content(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $html .= InlineCodes::encode($child->data);
            } elseif ($child instanceof DOMElement) {
                $html .= match ($child->localName) {
                    'bpt', 'ept', 'ph', 'it' => $child->textContent,
                    default => $this->content($child),
                };
            }
        }

        return $html;
    }
}
