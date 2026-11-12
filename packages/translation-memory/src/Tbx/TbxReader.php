<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tbx;

use DOMElement;
use Phil\TranslationMemory\Exception\InvalidLanguage;
use Phil\TranslationMemory\Exception\InvalidTbx;
use Phil\TranslationMemory\Exception\InvalidTerm;
use Phil\TranslationMemory\Glossary\Glossary;
use Phil\TranslationMemory\Glossary\Term;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Text;
use Phil\TranslationMemory\Xml;

/**
 * Reads the terms of one pair of languages from a TBX file: TBX of ISO 30042:2019 — `<tbx>`, `<conceptEntry>`,
 * `<langSec>`, `<termSec>` —, and the TBX of 2008 — `<martif>`, `<termEntry>`, `<langSet>`, `<tig>` and `<ntig>`.
 *
 * A file that declares a document type is refused before it is parsed, and nothing is fetched from the network. In
 * each entry that holds both languages, the source term is the preferred one — or the first that is not deprecated —,
 * the other source terms its forms; the target terms that are not deprecated are its translations, the deprecated,
 * superseded or not recommended ones are refused. An entry's `<note>`, or else its definition, is the note. A
 * language matches its regional variants: an entry in `fr` is read for `fr-CA` when it has no `fr-CA` section.
 */
final readonly class TbxReader
{
    /** The most entries a file holds. */
    public const int MAX_ENTRIES = 100_000;

    /**
     * @throws InvalidTbx when the text is not well-formed XML, declares a document type, is not TBX, holds a language
     *                    tag that is not one, or more than 100 000 entries
     */
    public function read(string $xml, LanguageTag $sourceLanguage, LanguageTag $targetLanguage): Glossary
    {
        $root = Xml::root($xml);

        [$entryName, $languageName] = match ($root?->localName) {
            'tbx' => ['conceptEntry', 'langSec'],
            'martif' => ['termEntry', 'langSet'],
            default => throw InvalidTbx::because('the text is not well-formed XML with a <tbx> or <martif> root and no document type.'),
        };

        $text = Xml::children($root, 'text')[0] ?? null;
        $body = null === $text ? null : Xml::children($text, 'body')[0] ?? null;
        $entries = null === $body ? [] : Xml::children($body, $entryName);

        if (\count($entries) > self::MAX_ENTRIES) {
            throw InvalidTbx::because(\sprintf('a file holds at most %d entries.', self::MAX_ENTRIES));
        }

        try {
            $terms = [];

            foreach ($entries as $entry) {
                $term = $this->term($entry, $languageName, $sourceLanguage, $targetLanguage);

                if ($term instanceof Term) {
                    $terms[] = $term;
                }
            }
        } catch (InvalidLanguage $invalidLanguage) {
            throw InvalidTbx::because($invalidLanguage->getMessage());
        }

        return new Glossary($sourceLanguage, $targetLanguage, $terms);
    }

    private function term(DOMElement $entry, string $languageName, LanguageTag $sourceLanguage, LanguageTag $targetLanguage): ?Term
    {
        $sections = [];

        foreach (Xml::children($entry, $languageName) as $section) {
            $sections[LanguageTag::of(Xml::language($section))->value] = $this->terms($section);
        }

        $source = $this->section($sections, $sourceLanguage) ?? [];
        $target = $this->section($sections, $targetLanguage);
        $allowed = array_map(strval(...), array_keys(array_filter($source, $this->allowed(...))));

        if ([] === $allowed || null === $target) {
            return null;
        }

        $main = array_find($allowed, static fn (string $text): bool => TbxWriter::PREFERRED === $source[$text]) ?? $allowed[0];
        $translations = array_keys(array_filter($target, $this->allowed(...)));
        $forbidden = array_keys(array_diff_key($target, array_flip($translations)));

        try {
            return new Term(
                $main,
                array_map(strval(...), $translations),
                array_map(strval(...), $forbidden),
                note: $this->note($entry),
                forms: $allowed,
            );
        } catch (InvalidTerm) {
            return null;
        }
    }

    /**
     * The section of a language, or of the language it is a variant of.
     *
     * @param array<string, array<string, string>> $sections
     *
     * @return ?array<string, string>
     */
    private function section(array $sections, LanguageTag $language): ?array
    {
        for ($tag = $language; $tag instanceof LanguageTag; $tag = $tag->parent()) {
            if (isset($sections[$tag->value])) {
                return $sections[$tag->value];
            }
        }

        return null;
    }

    /**
     * The terms of a language section, each with its administrative status, the first one kept for a term written
     * twice.
     *
     * @return array<string, string>
     */
    private function terms(DOMElement $section): array
    {
        $terms = [];

        foreach ($this->termGroups($section) as [$text, $status]) {
            $terms[$text] ??= $status;
        }

        return $terms;
    }

    /**
     * Whether a status lets a term be used: any but deprecated, superseded or not recommended.
     */
    private function allowed(string $status): bool
    {
        return 1 !== preg_match('/^(?:deprecated|superseded|notRecommended)/', $status);
    }

    /**
     * Each term of a language section, as normalized text, and its administrative status.
     *
     * @return list<array{string, string}>
     */
    private function termGroups(DOMElement $section): array
    {
        $groups = [];

        foreach (Xml::children($section, 'termSec', 'tig', 'ntig') as $group) {
            $holder = 'ntig' === $group->localName ? Xml::children($group, 'termGrp')[0] ?? $group : $group;
            $term = Xml::children($holder, 'term')[0] ?? null;
            $text = null === $term ? '' : Text::normalize($term->textContent);

            if ('' === $text) {
                continue;
            }

            $status = '';

            foreach (Xml::children($holder, 'termNote') as $note) {
                $status = \in_array($note->getAttribute('type'), ['administrativeStatus', 'normativeAuthorization'], true) ? trim($note->textContent) : $status;
            }

            $groups[] = [$text, $status];
        }

        return $groups;
    }

    private function note(DOMElement $entry): ?string
    {
        foreach (Xml::children($entry, 'note', 'descrip') as $note) {
            if ('note' === $note->localName || 'definition' === $note->getAttribute('type')) {
                $text = Text::normalize($note->textContent);

                return '' === $text ? null : $text;
            }
        }

        return null;
    }
}
