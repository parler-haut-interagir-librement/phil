<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tbx;

use DOMDocument;
use DOMElement;
use DOMText;
use Phil\TranslationMemory\Glossary\Glossary;
use Phil\TranslationMemory\Glossary\Term;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Xml;

/**
 * Writes a glossary as TBX, the exchange format of terminology (ISO 30042:2019), in the TBX-Basic dialect and its
 * DCA style: one `<conceptEntry>` per term, a `<langSec>` per language, a `<termSec>` per term.
 *
 * In the source language, the term is the preferred term and its forms are admitted; in the target language, the
 * first translation — or the term itself, when it is kept — is preferred, the other translations and their forms
 * admitted, and the refused translations deprecated. The note goes into a `<note>` of the entry. TBX has no place for
 * case: a case-sensitive term is read back as one that ignores case.
 */
final readonly class TbxWriter
{
    public const string TBX_NAMESPACE = 'urn:iso:std:iso:30042:ed-2';

    public const string PREFERRED = 'preferredTerm-admn-sts';

    public const string ADMITTED = 'admittedTerm-admn-sts';

    public const string DEPRECATED = 'deprecatedTerm-admn-sts';

    public function write(Glossary $glossary): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $tbx = new DOMElement('tbx', null, self::TBX_NAMESPACE);
        $document->appendChild($tbx);
        $tbx->setAttribute('type', 'TBX-Basic');
        $tbx->setAttribute('style', 'dca');
        $tbx->setAttributeNS(Xml::XML_NAMESPACE, 'xml:lang', $glossary->sourceLanguage->value);

        $sourceDesc = $this->element($this->element($this->element($tbx, 'tbxHeader'), 'fileDesc'), 'sourceDesc');
        $this->element($sourceDesc, 'p', 'phil/translation-memory');
        $body = $this->element($this->element($tbx, 'text'), 'body');

        foreach ($glossary->terms as $index => $term) {
            $entry = $this->element($body, 'conceptEntry');
            $entry->setAttribute('id', 'c'.($index + 1));

            if (null !== $term->note) {
                $this->element($entry, 'note', $term->note);
            }

            $this->language($entry, $glossary->sourceLanguage, [[$term->source, self::PREFERRED], ...array_map(static fn (string $form): array => [$form, self::ADMITTED], $term->forms)]);
            $this->language($entry, $glossary->targetLanguage, $this->targets($term));
        }

        return (string) $document->saveXML();
    }

    /**
     * The terms of the target language and their status, the preferred one first.
     *
     * @return list<array{string, string}>
     */
    private function targets(Term $term): array
    {
        $targets = [];

        foreach ($term->accepted() as $index => $text) {
            $targets[] = [$text, 0 === $index ? self::PREFERRED : self::ADMITTED];
        }

        foreach (array_diff($term->forbidden, $term->accepted()) as $text) {
            $targets[] = [$text, self::DEPRECATED];
        }

        return $targets;
    }

    /**
     * @param list<array{string, string}> $terms each term and its administrative status
     */
    private function language(DOMElement $entry, LanguageTag $language, array $terms): void
    {
        $langSec = $this->element($entry, 'langSec');
        $langSec->setAttributeNS(Xml::XML_NAMESPACE, 'xml:lang', $language->value);

        foreach ($terms as [$text, $status]) {
            $termSec = $this->element($langSec, 'termSec');
            $this->element($termSec, 'term', $text);
            $this->element($termSec, 'termNote', $status)->setAttribute('type', 'administrativeStatus');
        }
    }

    private function element(DOMElement $parent, string $name, ?string $text = null): DOMElement
    {
        $element = new DOMElement($name, null, self::TBX_NAMESPACE);
        $parent->appendChild($element);

        if (null !== $text) {
            $element->appendChild(new DOMText($text));
        }

        return $element;
    }
}
