<?php

declare(strict_types=1);

namespace Phil\Citations\Import;

use Phil\Citations\Exception\InvalidImport;
use Phil\Citations\Internal\DateText;
use Phil\Citations\Model\CslDate;
use Phil\Citations\Model\DateVariable;
use Phil\Citations\Model\ItemType;
use Phil\Citations\Model\Name;
use Phil\Citations\Model\NameVariable;
use Phil\Citations\Model\Reference;
use Transliterator;

/**
 * References read from RIS, the tagged format of reference managers and library catalogues: records from `TY  - `
 * to `ER  - `, one tag per line, a line without a tag continuing the value before it, names as « Last, First,
 * Suffix ».
 *
 * Each record becomes a CSL-JSON reference; its id is the `ID` tag, or else made from the first author's family and
 * the year (`smith2020`, then `smith2020a`), or `ref` and the record's number. The tags CSL has no place for are left
 * out. The input is bounded in size, records and field length, and must be UTF-8.
 */
final readonly class RisReader
{
    private const array TYPES = [
        'ABST' => ItemType::ArticleJournal, 'BILL' => ItemType::Bill, 'BLOG' => ItemType::PostWeblog,
        'BOOK' => ItemType::Book, 'CASE' => ItemType::LegalCase, 'CHAP' => ItemType::Chapter,
        'COMP' => ItemType::Software, 'CONF' => ItemType::PaperConference, 'CPAPER' => ItemType::PaperConference,
        'DATA' => ItemType::Dataset, 'DICT' => ItemType::EntryDictionary, 'EBOOK' => ItemType::Book,
        'ECHAP' => ItemType::Chapter, 'EDBOOK' => ItemType::Book, 'EJOUR' => ItemType::ArticleJournal,
        'ELEC' => ItemType::Webpage, 'ENCYC' => ItemType::EntryEncyclopedia, 'FIGURE' => ItemType::Figure,
        'GEN' => ItemType::Document, 'HEAR' => ItemType::Hearing, 'ICOMM' => ItemType::PersonalCommunication,
        'JFULL' => ItemType::Periodical, 'JOUR' => ItemType::ArticleJournal, 'LEGAL' => ItemType::Legislation,
        'MAP' => ItemType::Map, 'MGZN' => ItemType::ArticleMagazine, 'MPCT' => ItemType::MotionPicture,
        'MUSIC' => ItemType::MusicalScore, 'NEWS' => ItemType::ArticleNewspaper, 'PAMP' => ItemType::Pamphlet,
        'PAT' => ItemType::Patent, 'PCOMM' => ItemType::PersonalCommunication, 'RPRT' => ItemType::Report,
        'SER' => ItemType::Book, 'SLIDE' => ItemType::Speech, 'SOUND' => ItemType::Song, 'STAND' => ItemType::Standard,
        'STAT' => ItemType::Legislation, 'THES' => ItemType::Thesis, 'UNPB' => ItemType::Manuscript,
        'VIDEO' => ItemType::MotionPicture, 'WEB' => ItemType::Webpage,
    ];

    private const array FIELDS = [
        'TI' => 'title', 'T1' => 'title', 'CT' => 'title', 'T2' => 'container-title', 'JO' => 'container-title',
        'JF' => 'container-title', 'BT' => 'container-title', 'JA' => 'container-title-short',
        'J2' => 'container-title-short', 'T3' => 'collection-title', 'VL' => 'volume', 'IS' => 'issue',
        'PB' => 'publisher', 'CY' => 'publisher-place', 'PP' => 'publisher-place', 'DO' => 'DOI', 'UR' => 'URL',
        'AB' => 'abstract', 'N2' => 'abstract', 'N1' => 'note', 'ET' => 'edition', 'LA' => 'language',
        'M3' => 'genre', 'CN' => 'call-number', 'AN' => 'archive_location', 'DB' => 'archive', 'NV' => 'number-of-volumes',
    ];

    private const array NAMES = [
        'AU' => NameVariable::Author, 'A1' => NameVariable::Author, 'A2' => NameVariable::Editor,
        'ED' => NameVariable::Editor, 'A3' => NameVariable::CollectionEditor, 'A4' => NameVariable::Translator,
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
    public function read(string $ris): array
    {
        if (\strlen($ris) > $this->limits->maxBytes) {
            throw InvalidImport::at('RIS', 1, \sprintf('the file is longer than %d bytes', $this->limits->maxBytes));
        }

        if (!mb_check_encoding($ris, 'UTF-8')) {
            throw InvalidImport::at('RIS', 1, 'the file is not UTF-8');
        }

        $references = [];
        $ids = [];
        $record = null;
        $tag = null;

        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $ris)) as $index => $line) {
            $line = 0 === $index ? (string) preg_replace('/^\x{FEFF}/u', '', $line) : $line;

            if (1 !== preg_match('/^([A-Z][A-Z0-9])  -(?: (.*))?$/D', $line, $matches)) {
                if (null !== $record && null !== $tag && '' !== trim($line)) {
                    $last = array_key_last($record[$tag]);
                    $record[$tag][$last] = $this->bounded($record[$tag][$last].' '.trim($line), $index + 1);
                }

                continue;
            }

            [$tag, $value] = [$matches[1], trim($matches[2] ?? '')];

            if ('TY' === $tag) {
                if (null !== $record) {
                    throw InvalidImport::at('RIS', $index + 1, 'a record starts before the one before it ends');
                }

                if (\count($references) >= $this->limits->maxEntries) {
                    throw InvalidImport::at('RIS', $index + 1, \sprintf('the file holds more than %d records', $this->limits->maxEntries));
                }

                $record = ['TY' => [$value]];
            } elseif (null === $record) {
                throw InvalidImport::at('RIS', $index + 1, \sprintf('the tag %s is outside a record', $tag));
            } elseif ('ER' === $tag) {
                $references[] = $this->reference($record, $ids, \count($references) + 1);
                $record = null;
                $tag = null;
            } else {
                $record[$tag][] = $this->bounded($value, $index + 1);
            }
        }

        if (null !== $record) {
            throw InvalidImport::at('RIS', substr_count($ris, "\n") + 1, 'the last record has no ER tag');
        }

        return $references;
    }

    private function bounded(string $value, int $line): string
    {
        if (\strlen($value) > $this->limits->maxFieldLength) {
            throw InvalidImport::at('RIS', $line, \sprintf('a value is longer than %d bytes', $this->limits->maxFieldLength));
        }

        return $value;
    }

    /**
     * @param array<string, list<string>> $record
     * @param array<string, true>         $ids    the ids given so far, to keep made ones apart
     */
    private function reference(array $record, array &$ids, int $number): Reference
    {
        $fields = [];

        foreach (self::FIELDS as $tag => $field) {
            $value = $record[$tag][0] ?? '';

            if ('' !== $value && !isset($fields[$field])) {
                $fields[$field] = $value;
            }
        }

        $type = self::TYPES[$record['TY'][0]] ?? ItemType::Document;
        $pages = array_filter([$record['SP'][0] ?? '', $record['EP'][0] ?? ''], static fn (string $page): bool => '' !== $page);

        if ([] !== $pages) {
            $fields['page'] = implode('-', $pages);
        }

        $serial = $record['SN'][0] ?? '';

        if ('' !== $serial) {
            $fields[\in_array($type, [ItemType::ArticleJournal, ItemType::ArticleMagazine, ItemType::ArticleNewspaper, ItemType::Periodical], true) ? 'ISSN' : 'ISBN'] = $serial;
        }

        $names = [];

        foreach (self::NAMES as $tag => $variable) {
            foreach ($record[$tag] ?? [] as $value) {
                if (\count($names[$variable->value] ?? []) < $this->limits->maxNames && '' !== trim($value, ' ,')) {
                    $names[$variable->value][] = $this->name($value);
                }
            }
        }

        $dates = [];
        $issued = $record['PY'][0] ?? $record['Y1'][0] ?? $record['DA'][0] ?? '';

        if ('' !== $issued) {
            $dates[DateVariable::Issued->value] = DateText::parse($issued);
        }

        $id = trim($record['ID'][0] ?? '');
        $id = '' !== $id ? $id : $this->madeId($names[NameVariable::Author->value][0] ?? null, $dates[DateVariable::Issued->value] ?? null, $ids, $number);
        $ids[$id] = true;

        return new Reference($id, $type, $names, $dates, $fields);
    }

    private function name(string $value): Name
    {
        $parts = array_map(trim(...), explode(',', $value, 3));

        if (1 === \count($parts) || '' === $parts[0]) {
            return Name::literal(trim($value, ' ,'));
        }

        return new Name($parts[0], '' === $parts[1] ? null : $parts[1], suffix: '' === ($parts[2] ?? '') ? null : $parts[2]);
    }

    /**
     * @param array<string, true> $ids
     */
    private function madeId(?Name $author, ?CslDate $issued, array $ids, int $number): string
    {
        $family = $author instanceof Name ? (string) Transliterator::create('Any-Latin; Latin-ASCII; Lower()')?->transliterate($author->familyName()) : '';
        $family = (string) preg_replace('/[^a-z0-9]/', '', $family);

        $year = $issued?->startYear();

        $base = '' === $family || null === $year ? 'ref'.$number : $family.$year;
        $id = $base;

        for ($suffix = 'a'; isset($ids[$id]); $suffix = str_increment($suffix)) {
            $id = $base.$suffix;
        }

        return $id;
    }
}
