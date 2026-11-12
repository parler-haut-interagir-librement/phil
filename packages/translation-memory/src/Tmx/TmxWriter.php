<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tmx;

use DOMDocument;
use DOMElement;
use DOMText;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Memory\MemoryUnit;
use Phil\TranslationMemory\Xliff\InlineCodes;
use Phil\TranslationMemory\Xml;

/**
 * Writes translation units as TMX 1.4b, the exchange format of translation memories: one `<tu>` per unit, its source
 * and target as `<tuv>` of their languages, the origin of the unit as a `x-origin` property.
 *
 * Tags stay out of the words a tool may edit: an opening tag and the closing tag that matches it become `<bpt>` and
 * `<ept>` sharing a number, any other tag a `<ph>`, each holding the HTML it stands for.
 */
final readonly class TmxWriter
{
    public const string ORIGIN = 'x-origin';

    /**
     * @param iterable<MemoryUnit> $units the units of one source language
     */
    public function write(LanguageTag $sourceLanguage, iterable $units): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $tmx = $document->createElement('tmx');
        $tmx->setAttribute('version', '1.4');

        $document->appendChild($tmx);

        $header = $this->element($tmx, 'header');
        foreach (['creationtool' => 'phil/translation-memory', 'creationtoolversion' => '0.1', 'segtype' => 'sentence', 'o-tmf' => 'phil', 'adminlang' => 'en', 'srclang' => $sourceLanguage->value, 'datatype' => 'html'] as $name => $value) {
            $header->setAttribute($name, $value);
        }

        $body = $this->element($tmx, 'body');

        foreach ($units as $unit) {
            if (!$unit->sourceLanguage->equals($sourceLanguage)) {
                continue;
            }

            $tu = $this->element($body, 'tu');

            if (null !== $unit->origin) {
                $this->element($tu, 'prop', $unit->origin)->setAttribute('type', self::ORIGIN);
            }

            $this->variant($tu, $unit->sourceLanguage, $unit->source);
            $this->variant($tu, $unit->targetLanguage, $unit->target);
        }

        return (string) $document->saveXML();
    }

    private function variant(DOMElement $tu, LanguageTag $language, string $html): void
    {
        $tuv = $this->element($tu, 'tuv');
        $tuv->setAttributeNS(Xml::XML_NAMESPACE, 'xml:lang', $language->value);

        $seg = $this->element($tuv, 'seg');
        $tokens = InlineCodes::tokens($html);

        $codes = $this->pairs($tokens);

        foreach ($tokens as $index => [$tag, $token]) {
            if (!$tag) {
                $seg->appendChild(new DOMText(InlineCodes::decode($token)));

                continue;
            }

            [$name, $number] = $codes[$index];
            $element = $this->element($seg, $name, $token);

            if (null !== $number) {
                $element->setAttribute('i', (string) $number);
            }
        }
    }

    /**
     * The code each tag becomes: `bpt` and `ept` with their shared number, `ph` without one; words have none.
     *
     * @param list<array{bool, string}> $tokens
     *
     * @return array<int, array{string, ?int}>
     */
    private function pairs(array $tokens): array
    {
        $codes = [];
        $open = [];
        $number = 0;

        foreach ($tokens as $index => [$tag, $token]) {
            if (!$tag) {
                continue;
            }

            $codes[$index] = ['ph', null];

            if (1 !== preg_match('#^<(/?)([A-Za-z][A-Za-z0-9-]*)[^>]*?(/?)>#', $token, $match) || '/' === $match[3]) {
                continue;
            }

            $name = strtolower($match[2]);

            if ('' === $match[1]) {
                $open[] = [$name, $index];

                continue;
            }

            for ($at = \count($open) - 1; $at >= 0; --$at) {
                if ($open[$at][0] === $name) {
                    ++$number;
                    $codes[$open[$at][1]] = ['bpt', $number];
                    $codes[$index] = ['ept', $number];
                    array_splice($open, $at);

                    break;
                }
            }
        }

        return $codes;
    }

    private function element(DOMElement $parent, string $name, ?string $text = null): DOMElement
    {
        $element = new DOMElement($name);
        $parent->appendChild($element);

        if (null !== $text) {
            $element->appendChild(new DOMText($text));
        }

        return $element;
    }
}
