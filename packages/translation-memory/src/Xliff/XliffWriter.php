<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Xliff;

use DOMDocument;
use DOMElement;
use DOMText;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\SegmentState;

/**
 * Writes XLIFF 2.1 on PHP's DOM.
 *
 * One `<file>` holds one `<unit>` per segment, identified `u1`, `u2`… and named after the key of its segment. The
 * tags of a text become `<ph>` codes whose HTML is kept in the unit's `<originalData>`, so that a translation tool
 * shows them as codes to place rather than as text to translate; a tag of the target that the source also holds
 * takes the same code. The state of each segment is the XLIFF state — `initial`, `translated`, `reviewed` — and a
 * suggested or stale target says so in `subState`, `phil:suggested` or `phil:stale`.
 */
final readonly class XliffWriter
{
    public const string XLIFF_NAMESPACE = 'urn:oasis:names:tc:xliff:document:2.0';

    public function write(XliffFile $file): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = new DOMElement('xliff', null, self::XLIFF_NAMESPACE);
        $document->appendChild($root);
        $root->setAttribute('version', '2.1');
        $root->setAttribute('srcLang', $file->sourceLanguage->value);

        if ($file->targetLanguage instanceof LanguageTag) {
            $root->setAttribute('trgLang', $file->targetLanguage->value);
        }

        $element = $this->element($root, 'file');
        $element->setAttribute('id', 'f1');
        $element->setAttribute('original', $file->original);

        foreach ($file->units as $index => $unit) {
            $this->unit($element, $unit, 'u'.($index + 1));
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n".$document->saveXML($root)."\n";
    }

    private function unit(DOMElement $file, XliffUnit $unit, string $id): void
    {
        $element = $this->element($file, 'unit');
        $element->setAttribute('id', $id);
        $element->setAttribute('name', $unit->key);

        if (null !== $unit->note) {
            $this->element($this->element($element, 'notes'), 'note', $unit->note)->setAttribute('category', 'phil:previous-source');
        }

        $codes = [];
        $source = $this->runs($unit->source, $codes, []);
        $target = null === $unit->target ? null : $this->runs($unit->target, $codes, $source);

        if ([] !== $codes) {
            $data = $this->element($element, 'originalData');

            foreach ($codes as $code => $html) {
                $this->element($data, 'data', $html)->setAttribute('id', 'd'.$code);
            }
        }

        $segment = $this->element($element, 'segment');
        [$state, $subState] = match ($unit->state) {
            SegmentState::Untranslated => ['initial', null],
            SegmentState::Suggested => ['initial', 'phil:suggested'],
            SegmentState::Translated => ['translated', null],
            SegmentState::Reviewed => ['reviewed', null],
            SegmentState::Stale => ['initial', 'phil:stale'],
        };
        $segment->setAttribute('state', $state);

        if (null !== $subState) {
            $segment->setAttribute('subState', $subState);
        }

        $this->content($this->element($segment, 'source'), $source);

        if (null !== $target) {
            $this->content($this->element($segment, 'target'), $target);
        }
    }

    /**
     * The runs of a text, each tag replaced by the number of its code: a tag the source holds, and no earlier tag of
     * the target took, reuses the source's code; any other gets a new one.
     *
     * @param array<int, string> $codes  the HTML of each code by number, filled as codes are made
     * @param list<int|string>   $source the runs of the source, when the text is its target
     *
     * @return list<int|string> each run: words, or the number of a code
     */
    private function runs(string $html, array &$codes, array $source): array
    {
        $free = [];

        foreach ($source as $run) {
            if (\is_int($run)) {
                $free[$codes[$run]][] = $run;
            }
        }

        $runs = [];

        foreach (InlineCodes::tokens($html) as [$tag, $token]) {
            if (!$tag) {
                $runs[] = $token;
            } elseif ([] !== ($free[$token] ?? [])) {
                $runs[] = array_shift($free[$token]);
            } else {
                $code = \count($codes) + 1;
                $codes[$code] = $token;
                $runs[] = $code;
            }
        }

        return $runs;
    }

    /**
     * @param list<int|string> $runs
     */
    private function content(DOMElement $element, array $runs): void
    {
        foreach ($runs as $run) {
            if (\is_int($run)) {
                $placeholder = $this->element($element, 'ph');
                $placeholder->setAttribute('id', (string) $run);
                $placeholder->setAttribute('dataRef', 'd'.$run);
            } else {
                $element->appendChild(new DOMText(InlineCodes::decode($run)));
            }
        }
    }

    private function element(DOMElement $parent, string $name, ?string $text = null): DOMElement
    {
        $element = new DOMElement($name, null, self::XLIFF_NAMESPACE);
        $parent->appendChild($element);

        if (null !== $text) {
            $element->appendChild(new DOMText($text));
        }

        return $element;
    }
}
