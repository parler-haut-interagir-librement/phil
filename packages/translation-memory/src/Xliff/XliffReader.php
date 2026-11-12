<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Xliff;

use DOMDocument;
use DOMDocumentType;
use DOMElement;
use DOMNode;
use DOMText;
use Phil\TranslationMemory\Exception\InvalidLanguage;
use Phil\TranslationMemory\Exception\InvalidXliff;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\SegmentState;
use Phil\TranslationMemory\Text;

/**
 * Reads XLIFF 2.0, 2.1 and 2.2, and XLIFF 1.2, on PHP's DOM.
 *
 * A file that declares a document type is refused before it is parsed, and nothing is fetched from the network. The
 * key of a unit is its `name` — its `resname` in 1.2 — or else its identifier. Inline codes come back as the HTML they
 * stand for: in 2.x the `<originalData>` a code refers to, in 1.2 the native code inside `<ph>`, `<bpt>`, `<ept>` and
 * `<it>`; the other codes keep their words only. Text is escaped back as inline HTML.
 *
 * The state of a unit is read from the file: `initial` with a target, or a 1.2 state that asks for work, is
 * `suggested`; `translated` is `translated`; `reviewed`, `final` and `signed-off` are `reviewed`; a unit without target
 * is `untranslated`. A unit of several segments joins them, and takes the least advanced of their states.
 */
final readonly class XliffReader
{
    public const string NAMESPACE_1_2 = 'urn:oasis:names:tc:xliff:document:1.2';

    /**
     * @throws InvalidXliff when the text is not well-formed XML, declares a document type, or is not XLIFF 1.2 or
     *                      2.x with its languages
     */
    public function read(string $xml): XliffFile
    {
        if (false !== stripos($xml, '<!DOCTYPE')) {
            throw InvalidXliff::because('a document type declaration is not allowed.');
        }

        $document = new DOMDocument();
        $errors = libxml_use_internal_errors(true);
        if ('' !== $xml) {
            $document->loadXML($xml, \LIBXML_NONET);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($errors);
        $root = $document->documentElement;

        if ($document->doctype instanceof DOMDocumentType) {
            throw InvalidXliff::because('a document type declaration is not allowed.');
        }

        if (!$root instanceof DOMElement) {
            throw InvalidXliff::because('it is not well-formed XML.');
        }

        try {
            return match (true) {
                'xliff' === $root->localName && XliffWriter::XLIFF_NAMESPACE === $root->namespaceURI && str_starts_with($root->getAttribute('version'), '2.') => $this->version2($root),
                'xliff' === $root->localName && self::NAMESPACE_1_2 === $root->namespaceURI && '1.2' === $root->getAttribute('version') => $this->version12($root),
                default => throw InvalidXliff::because('the root is not an XLIFF 1.2 or 2.x element.'),
            };
        } catch (InvalidLanguage $invalidLanguage) {
            throw InvalidXliff::because($invalidLanguage->getMessage());
        }
    }

    private function version2(DOMElement $root): XliffFile
    {
        $target = $root->getAttribute('trgLang');
        $units = [];
        $original = '';

        foreach ($this->children($root, 'file') as $file) {
            $original = '' === $original ? $file->getAttribute('original') : $original;

            foreach ($file->getElementsByTagNameNS(XliffWriter::XLIFF_NAMESPACE, 'unit') as $unit) {
                $units[] = $this->unit2($unit);
            }
        }

        return new XliffFile(LanguageTag::of($root->getAttribute('srcLang')), '' === $target ? null : LanguageTag::of($target), $original, $units);
    }

    private function unit2(DOMElement $unit): XliffUnit
    {
        $data = [];

        foreach ($this->children($unit, 'originalData') as $originalData) {
            foreach ($this->children($originalData, 'data') as $code) {
                $data[$code->getAttribute('id')] = $code->textContent;
            }
        }

        $source = '';
        $target = '';
        $complete = true;
        $segments = 0;
        $state = SegmentState::Reviewed;

        foreach ($this->children($unit, 'segment', 'ignorable') as $part) {
            $partSource = $this->first($part, 'source');
            $partTarget = $this->first($part, 'target');
            $read = $partSource instanceof DOMElement ? $this->content2($partSource, $data) : '';
            $source .= $read;

            if ('ignorable' === $part->localName) {
                $target .= $partTarget instanceof DOMElement ? $this->content2($partTarget, $data) : $read;

                continue;
            }

            ++$segments;
            $translated = $partTarget instanceof DOMElement ? $this->content2($partTarget, $data) : null;
            $complete = $complete && null !== $translated && Text::hasWords($translated);
            $target .= $translated ?? '';
            $state = $this->least($state, match ($part->getAttribute('state')) {
                'translated' => SegmentState::Translated,
                'reviewed', 'final' => SegmentState::Reviewed,
                default => SegmentState::Suggested,
            });
        }

        $complete = $complete && 0 < $segments;

        return new XliffUnit($this->key($unit, 'name'), $source, $complete ? $target : null, $complete ? $state : SegmentState::Untranslated);
    }

    /**
     * @param array<string, string> $data
     */
    private function content2(DOMNode $node, array $data): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $html .= InlineCodes::encode($child->data);
            } elseif ($child instanceof DOMElement) {
                $html .= match ($child->localName) {
                    'ph', 'sc', 'ec' => $data[$child->getAttribute('dataRef')] ?? '',
                    'pc' => ($data[$child->getAttribute('dataRefStart')] ?? '').$this->content2($child, $data).($data[$child->getAttribute('dataRefEnd')] ?? ''),
                    'cp' => InlineCodes::encode($this->character($child->getAttribute('hex'))),
                    default => $this->content2($child, $data),
                };
            }
        }

        return $html;
    }

    private function version12(DOMElement $root): XliffFile
    {
        $files = $this->children($root, 'file');
        $first = $files[0] ?? null;

        if (!$first instanceof DOMElement) {
            throw InvalidXliff::because('an XLIFF 1.2 file holds at least one <file>.');
        }

        $target = $first->getAttribute('target-language');
        $units = [];

        foreach ($files as $file) {
            foreach ($file->getElementsByTagNameNS(self::NAMESPACE_1_2, 'trans-unit') as $unit) {
                $units[] = $this->unit12($unit);
            }
        }

        return new XliffFile(LanguageTag::of($first->getAttribute('source-language')), '' === $target ? null : LanguageTag::of($target), $first->getAttribute('original'), $units);
    }

    private function unit12(DOMElement $unit): XliffUnit
    {
        $source = $this->first($unit, 'source');
        $source = $source instanceof DOMElement ? $this->content12($source) : '';

        $target = $this->first($unit, 'target');
        $translated = $target instanceof DOMElement ? $this->content12($target) : '';

        if (!$target instanceof DOMElement || !Text::hasWords($translated)) {
            return new XliffUnit($this->key($unit, 'resname'), $source);
        }

        $state = 'yes' === $unit->getAttribute('approved') ? SegmentState::Reviewed : match ($target->getAttribute('state')) {
            '', 'translated' => SegmentState::Translated,
            'final', 'signed-off' => SegmentState::Reviewed,
            default => SegmentState::Suggested,
        };

        return new XliffUnit($this->key($unit, 'resname'), $source, $translated, $state);
    }

    private function content12(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $html .= InlineCodes::encode($child->data);
            } elseif ($child instanceof DOMElement) {
                $html .= match ($child->localName) {
                    'ph', 'bpt', 'ept', 'it' => $child->textContent,
                    default => $this->content12($child),
                };
            }
        }

        return $html;
    }

    /**
     * The less advanced of two states of a segment with a target: suggested, then translated, then reviewed.
     */
    private function least(SegmentState $a, SegmentState $b): SegmentState
    {
        foreach ([SegmentState::Suggested, SegmentState::Translated] as $state) {
            if ($state === $a || $state === $b) {
                return $state;
            }
        }

        return SegmentState::Reviewed;
    }

    /**
     * The character of a code point written in hexadecimal, nothing when it is none.
     */
    private function character(string $hex): string
    {
        $character = ctype_xdigit($hex) ? mb_chr((int) hexdec($hex), 'UTF-8') : false;

        return false === $character ? '' : $character;
    }

    private function key(DOMElement $unit, string $name): string
    {
        $key = $unit->getAttribute($name);

        return '' === $key ? $unit->getAttribute('id') : $key;
    }

    private function first(DOMElement $parent, string $name): ?DOMElement
    {
        return $this->children($parent, $name)[0] ?? null;
    }

    /**
     * The child elements of an element with one of the names, in order.
     *
     * @return list<DOMElement>
     */
    private function children(DOMElement $parent, string ...$names): array
    {
        $children = [];

        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && \in_array($child->localName, $names, true)) {
                $children[] = $child;
            }
        }

        return $children;
    }
}
