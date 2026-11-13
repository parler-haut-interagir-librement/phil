<?php

declare(strict_types=1);

namespace Phil\Citations\Style;

use Phil\Citations\Cite;
use Phil\Citations\Contract\CitationStyle;
use Phil\Citations\Model\DateVariable;
use Phil\Citations\Model\ItemType;
use Phil\Citations\Model\Name;
use Phil\Citations\Model\NameVariable;
use Phil\Citations\Model\PartialDate;
use Phil\Citations\Model\Reference;
use Phil\Citations\RichText;
use Phil\Citations\Terms;

/**
 * What the styles of the library share: names in their natural or inverted order, lists of names, years, dates,
 * titles in italics or between quotation marks, editions, pages, locators and links. A style written in PHP may
 * extend it.
 */
abstract class AbstractStyle implements CitationStyle
{
    private const array CONTAINED = [
        ItemType::ArticleJournal, ItemType::ArticleMagazine, ItemType::ArticleNewspaper, ItemType::Article,
        ItemType::Review, ItemType::ReviewBook,
    ];

    private const array IN_A_BOOK = [
        ItemType::Chapter, ItemType::PaperConference, ItemType::Entry, ItemType::EntryDictionary,
        ItemType::EntryEncyclopedia,
    ];

    /**
     * An article in a periodical: journal, magazine, newspaper, review.
     */
    protected function isArticle(Reference $reference): bool
    {
        return \in_array($reference->type, self::CONTAINED, true);
    }

    /**
     * A part of a book: chapter, paper in proceedings, entry of a dictionary or an encyclopedia.
     */
    protected function isInBook(Reference $reference): bool
    {
        return \in_array($reference->type, self::IN_A_BOOK, true);
    }

    /**
     * The authors, else the editors as authors; the boolean tells the editors.
     *
     * @return array{list<Name>, bool}
     */
    protected function creators(Reference $reference): array
    {
        $authors = $reference->names(NameVariable::Author);

        return [] !== $authors ? [$authors, false] : [$reference->names(NameVariable::Editor), true];
    }

    /**
     * « John A. Smith Jr. », or « J. A. Smith Jr. » with initials.
     */
    protected function natural(Name $name, bool $initials = false): string
    {
        if ($name->isLiteral()) {
            return (string) $name->literal;
        }

        $given = $initials ? $name->initials() : (string) $name->given;
        $family = trim($name->droppingParticle.' '.$name->familyName());

        return trim(trim($given.' '.$family).' '.$name->suffix);
    }

    /**
     * « Smith, John A., Jr. », or « Smith, J. A., Jr. » with initials.
     */
    protected function inverted(Name $name, bool $initials = false): string
    {
        if ($name->isLiteral()) {
            return (string) $name->literal;
        }

        $given = trim(($initials ? $name->initials() : (string) $name->given).' '.$name->droppingParticle);

        return implode(', ', array_filter([$name->familyName(), $given, (string) $name->suffix], static fn (string $part): bool => '' !== $part));
    }

    /**
     * Names joined: « A », « A and B », « A, B, and C », the serial comma as the terms want it.
     *
     * @param list<string> $names
     */
    protected function listNames(array $names, string $and, Terms $terms): string
    {
        $last = array_pop($names);

        if (null === $last || [] === $names) {
            return (string) $last;
        }

        $comma = $terms->serialComma && \count($names) > 1 ? ',' : '';

        return implode(', ', $names).$comma.' '.$and.' '.$last;
    }

    /**
     * The year of the reference with its letter, or a literal date, or « n.d. ».
     */
    protected function year(Entry $entry, Terms $terms): string
    {
        $issued = $entry->reference->date(DateVariable::Issued);
        $year = $issued?->startYear();

        if (null === $year) {
            return ($issued->literal ?? $terms->noDate).('' === $entry->yearSuffix ? '' : '-'.$entry->yearSuffix);
        }

        return $this->issuedYear($entry->reference, $terms).$entry->yearSuffix;
    }

    /**
     * The year the reference was issued in, « 375 BCE » before the common era, or its literal date; empty without.
     */
    protected function issuedYear(Reference $reference, Terms $terms): string
    {
        $date = $reference->date(DateVariable::Issued);
        $year = $date?->startYear();

        if (null === $year) {
            return (string) $date?->literal;
        }

        return $year < 0 ? abs($year).' '.$terms->beforeCommonEra : (string) $year;
    }

    /**
     * A date with its month and day when known: « May 1, 2020 », « 1 mai 2020 », « May 2020 ».
     */
    protected function fullDate(PartialDate $date, Terms $terms): string
    {
        if (null === $date->month) {
            return (string) $date->year;
        }

        $month = $terms->month($date->month);

        if (null === $date->day) {
            return $month.' '.$date->year;
        }

        return 'fr' === $terms->language
            ? (1 === $date->day ? '1er' : (string) $date->day).' '.$month.' '.$date->year
            : $month.' '.$date->day.', '.$date->year;
    }

    /**
     * The title, in italics for a document published on its own, between quotation marks otherwise, a punctuation
     * mark after it placed inside or outside the marks as the terms want it.
     */
    protected function title(Reference $reference, Terms $terms, string $after = '', ?bool $italic = null): RichText
    {
        $title = (string) $reference->title();

        if ('' === $title) {
            return RichText::empty();
        }

        if ($italic ?? $reference->type->isStandalone()) {
            return RichText::concat(RichText::italic($title), $after);
        }

        return $this->quoted($title, $terms, $after);
    }

    protected function quoted(string $text, Terms $terms, string $after = ''): RichText
    {
        $endsWithMark = str_ends_with($text, '?') || str_ends_with($text, '!');
        $after = $endsWithMark && '.' === $after ? '' : $after;

        return RichText::text($terms->punctuationInsideQuotes
            ? $terms->openQuote.$text.$after.$terms->closeQuote
            : $terms->openQuote.$text.$terms->closeQuote.$after);
    }

    /**
     * The title up to its subtitle: « Being and Time » for « Being and Time: A Translation ».
     */
    protected function shortTitle(Reference $reference): string
    {
        $title = (string) ($reference->field('title-short') ?? $reference->title());
        $colon = strpos($title, ':');

        return false === $colon ? $title : rtrim(substr($title, 0, $colon));
    }

    /**
     * « 2nd ed. », « 2e éd. »; a text edition as it is, with the abbreviation.
     */
    protected function edition(Reference $reference, Terms $terms): string
    {
        $edition = $reference->field('edition');

        if (null === $edition) {
            return '';
        }

        return (ctype_digit($edition) ? $terms->ordinal((int) $edition) : $edition).' '.$terms->edition;
    }

    /**
     * A page range with an en dash: « 45–67 ».
     */
    protected function pages(string $pages): string
    {
        return (string) preg_replace('/\s*-+\s*/', '–', $pages);
    }

    /**
     * « p. 12 », « pp. 12–14 », « chap. 3 »; empty without a locator.
     */
    protected function locator(Cite $cite, Terms $terms): string
    {
        if (null === $cite->locator || '' === trim($cite->locator)) {
            return '';
        }

        $locator = $this->pages(trim($cite->locator));

        return $terms->locatorLabel($cite->label, $locator).' '.$locator;
    }

    /**
     * The DOI as a link (« https://doi.org/10.1000/1 »), else the URL; empty without either.
     */
    protected function link(Reference $reference): string
    {
        $doi = $reference->field('DOI');

        if (null !== $doi) {
            return str_starts_with($doi, 'http') ? $doi : 'https://doi.org/'.$doi;
        }

        return (string) $reference->field('URL');
    }

    /**
     * The parts that are not empty, each followed by the mark and a space — the mark inside or outside a closing
     * quotation mark as the terms want it, and not doubled after a part that already ends with it.
     *
     * @param list<RichText|string> $parts
     */
    protected function punctuated(string $mark, array $parts, Terms $terms): RichText
    {
        $result = RichText::empty();

        foreach ($parts as $part) {
            $part = $part instanceof RichText ? $part : RichText::text($part);

            if (!$part->isEmpty()) {
                $result = $result->isEmpty() ? $part : RichText::concat($result->punctuate($mark, $terms->closeQuote, $terms->punctuationInsideQuotes), ' ', $part);
            }
        }

        return $result;
    }

    protected function period(RichText $text, Terms $terms): RichText
    {
        return $text->sentence($terms->closeQuote, $terms->punctuationInsideQuotes);
    }

    /**
     * An abbreviation that opens a parenthesis or follows a name, capitalised where the language does so.
     */
    protected function capitalized(string $abbreviation, Terms $terms): string
    {
        return $terms->capitalizeAbbreviations ? ucfirst($abbreviation) : $abbreviation;
    }

    /**
     * The words before and after a cite, around its text.
     */
    protected function affixed(Cite $cite, RichText $text): RichText
    {
        return RichText::join(' ', [(string) $cite->prefix, $text, (string) $cite->suffix]);
    }
}
