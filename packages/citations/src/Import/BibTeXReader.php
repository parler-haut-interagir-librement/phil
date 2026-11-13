<?php

declare(strict_types=1);

namespace Phil\Citations\Import;

use Phil\Citations\Exception\InvalidImport;
use Phil\Citations\Exception\InvalidReference;
use Phil\Citations\Internal\BibTeXNames;
use Phil\Citations\Internal\BibTeXParser;
use Phil\Citations\Internal\DateText;
use Phil\Citations\Internal\LatexText;
use Phil\Citations\Model\CslDate;
use Phil\Citations\Model\DateVariable;
use Phil\Citations\Model\ItemType;
use Phil\Citations\Model\NameVariable;
use Phil\Citations\Model\PartialDate;
use Phil\Citations\Model\Reference;

/**
 * References read from BibTeX and its BibLaTeX fields: entries in braces or parentheses, `@string` macros and the
 * month macros, values joined by `#`, LaTeX accents and commands made plain Unicode, names in BibTeX's three forms,
 * and `crossref`, a child taking the fields it lacks from its parent and the parent's title as its `booktitle`.
 *
 * Each entry becomes a CSL-JSON reference whose id is the entry key; the types and fields map to CSL's, the fields
 * CSL has no place for are left out. The input is bounded in size, entries, field length and nesting, and must be
 * UTF-8.
 */
final readonly class BibTeXReader
{
    private const array TYPES = [
        'article' => ItemType::ArticleJournal, 'book' => ItemType::Book, 'booklet' => ItemType::Pamphlet,
        'collection' => ItemType::Book, 'conference' => ItemType::PaperConference, 'dataset' => ItemType::Dataset,
        'electronic' => ItemType::Webpage, 'inbook' => ItemType::Chapter, 'incollection' => ItemType::Chapter,
        'inproceedings' => ItemType::PaperConference, 'manual' => ItemType::Report, 'mastersthesis' => ItemType::Thesis,
        'misc' => ItemType::Document, 'online' => ItemType::Webpage, 'patent' => ItemType::Patent,
        'phdthesis' => ItemType::Thesis, 'proceedings' => ItemType::Book, 'report' => ItemType::Report,
        'software' => ItemType::Software, 'techreport' => ItemType::Report, 'thesis' => ItemType::Thesis,
        'unpublished' => ItemType::Manuscript, 'www' => ItemType::Webpage,
    ];

    private const array FIELDS = [
        'title' => 'title', 'journal' => 'container-title', 'journaltitle' => 'container-title',
        'booktitle' => 'container-title', 'publisher' => 'publisher', 'address' => 'publisher-place',
        'location' => 'publisher-place', 'volume' => 'volume', 'edition' => 'edition', 'pages' => 'page',
        'series' => 'collection-title', 'doi' => 'DOI', 'url' => 'URL', 'isbn' => 'ISBN', 'issn' => 'ISSN',
        'note' => 'note', 'abstract' => 'abstract', 'chapter' => 'chapter-number', 'type' => 'genre',
        'language' => 'language', 'howpublished' => 'medium',
    ];

    private const array PUBLISHERS = ['school', 'institution', 'organization'];

    private const array GENRES = ['mastersthesis' => "Master's thesis", 'phdthesis' => 'PhD thesis'];

    private const array MONTHS = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9,
        'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    public function __construct(
        private ImportLimits $limits = new ImportLimits(),
    ) {
    }

    /**
     * @return list<Reference>
     *
     * @throws InvalidImport
     */
    public function read(string $bibtex): array
    {
        if (\strlen($bibtex) > $this->limits->maxBytes) {
            throw InvalidImport::at('BibTeX', 1, \sprintf('the file is longer than %d bytes', $this->limits->maxBytes));
        }

        if (!mb_check_encoding($bibtex, 'UTF-8')) {
            throw InvalidImport::at('BibTeX', 1, 'the file is not UTF-8');
        }

        $entries = BibTeXParser::entries($bibtex, $this->limits);
        $byKey = [];

        foreach ($entries as $entry) {
            $byKey[strtolower($entry['key'])] ??= $entry['fields'];
        }

        $references = [];

        foreach ($entries as $entry) {
            $fields = $entry['fields'];
            $parent = $byKey[strtolower($fields['crossref'] ?? '')] ?? null;

            if (null !== $parent) {
                $fields += ['booktitle' => $parent['title'] ?? ''] + $parent;
            }

            $references[] = $this->reference($entry['type'], $entry['key'], $fields);
        }

        return $references;
    }

    /**
     * @param array<string, string> $raw
     */
    private function reference(string $type, string $key, array $raw): Reference
    {
        $fields = [];

        foreach ($raw as $name => $value) {
            $csl = self::FIELDS[$name] ?? null;

            if (null !== $csl && !isset($fields[$csl])) {
                $fields[$csl] = LatexText::plain($value);
            }
        }

        foreach (self::PUBLISHERS as $name) {
            if (!isset($fields['publisher']) && isset($raw[$name])) {
                $fields['publisher'] = LatexText::plain($raw[$name]);
            }
        }

        $itemType = self::TYPES[$type] ?? ItemType::Document;
        $number = isset($raw['number']) ? LatexText::plain($raw['number']) : null;

        if (null !== $number) {
            $fields[ItemType::ArticleJournal === $itemType ? 'issue' : 'number'] = $number;
        }

        if (!isset($fields['genre']) && isset(self::GENRES[$type])) {
            $fields['genre'] = self::GENRES[$type];
        }

        if (isset($fields['page'])) {
            $fields['page'] = str_replace('–', '-', $fields['page']);
        }

        if (isset($raw['subtitle'], $fields['title'])) {
            $fields['title'] .= ': '.LatexText::plain($raw['subtitle']);
        }

        $names = [];

        foreach (['author' => NameVariable::Author, 'editor' => NameVariable::Editor, 'translator' => NameVariable::Translator] as $name => $variable) {
            if (isset($raw[$name])) {
                $names[$variable->value] = BibTeXNames::parse($raw[$name], $this->limits->maxNames);
            }
        }

        $dates = [];
        $issued = $this->issued($raw);

        if ($issued instanceof CslDate) {
            $dates[DateVariable::Issued->value] = $issued;
        }

        if (isset($raw['urldate'])) {
            $dates[DateVariable::Accessed->value] = DateText::parse(LatexText::plain($raw['urldate']));
        }

        return new Reference($key, $itemType, $names, $dates, $fields);
    }

    /**
     * @param array<string, string> $raw
     */
    private function issued(array $raw): ?CslDate
    {
        if (isset($raw['date'])) {
            return DateText::parse(LatexText::plain($raw['date']));
        }

        if (!isset($raw['year'])) {
            return null;
        }

        $year = LatexText::plain($raw['year']);

        if (1 !== preg_match('/^-?\d{1,4}(?!\d)/', $year, $matches) || 0 === (int) $matches[0]) {
            return new CslDate(literal: $year);
        }

        $month = $this->month(LatexText::plain($raw['month'] ?? ''));
        $day = is_numeric($raw['day'] ?? null) ? (int) $raw['day'] : null;

        try {
            return new CslDate(new PartialDate((int) $matches[0], $month, null === $month ? null : $day));
        } catch (InvalidReference) {
            return new CslDate(new PartialDate((int) $matches[0]));
        }
    }

    private function month(string $month): ?int
    {
        if (is_numeric($month)) {
            $number = (int) $month;

            return $number >= 1 && $number <= 12 ? $number : null;
        }

        return self::MONTHS[strtolower(substr($month, 0, 3))] ?? null;
    }
}
