<?php

declare(strict_types=1);

namespace Phil\Citations\Style;

use Phil\Citations\Locator;
use Phil\Citations\Model\DateVariable;
use Phil\Citations\Model\ItemType;
use Phil\Citations\Model\Name;
use Phil\Citations\Model\NameVariable;
use Phil\Citations\Model\PartialDate;
use Phil\Citations\Model\Reference;
use Phil\Citations\RichText;
use Phil\Citations\Terms;

/**
 * A notes and bibliography style in the manner of Chicago: each citation is the text of a footnote, a full note the
 * first time a work is cited and a short one after, and the bibliography is in alphabetical order.
 *
 * « John Smith and Jane Jones, *Title* (Paris: Publisher, 2020), 12. » then « Smith and Jones, *Title*, 14. », and
 * in the bibliography « Smith, John, and Jane Jones. *Title*. Paris: Publisher, 2020. »
 *
 * A note names up to three authors, four or more as the first and « et al. »; an entry names up to ten, then the first
 * seven and « et al. ». A page locator is written bare (« 12 »), the others with their label (« chap. 3 »). The title
 * of a web page is quoted, as an article's. The note's number and its place are the
 * text's: the style writes only its content.
 */
final class Notes extends AbstractStyle
{
    public function order(): BibliographyOrder
    {
        return BibliographyOrder::Alphabetical;
    }

    public function yearSuffixes(): bool
    {
        return false;
    }

    public function citation(array $items, Terms $terms): RichText
    {
        $notes = [];

        foreach ($items as $item) {
            $reference = $item->entry->reference;
            $locator = Locator::Page === $item->cite->label ? $this->pages(trim((string) $item->cite->locator)) : $this->locator($item->cite, $terms);
            $note = $item->first ? $this->fullNote($reference, $locator, $terms) : $this->shortNote($reference, $locator, $terms);
            $notes[] = $this->affixed($item->cite, $note);
        }

        return $this->period(RichText::join('; ', $notes), $terms);
    }

    public function entry(Entry $entry, Terms $terms): RichText
    {
        $reference = $entry->reference;
        [$creators, $editors] = $this->creators($reference);
        $names = $this->entryNames($creators, $terms);

        if ($editors && '' !== $names) {
            $names .= ', '.(\count($creators) > 1 ? $terms->editors : $terms->editor);
        }

        $parts = [$names, $this->noteTitle($reference, $terms)];

        if ($this->isArticle($reference)) {
            $parts[] = RichText::concat($this->periodical($reference, $terms), $this->articlePages($reference, '', $terms));
        } elseif ($this->isInBook($reference)) {
            $page = $reference->field('page');
            $parts[] = $this->punctuated(',', [
                RichText::concat(ucfirst($terms->in).' ', RichText::italic((string) $reference->field('container-title'))),
                $this->editedBy($reference, $terms, $editors),
                null === $page ? '' : $this->pages($page),
            ], $terms);
            $parts[] = $this->publication($reference, $terms);
        } else {
            $parts[] = ucfirst($this->editedBy($reference, $terms, $editors));
            $parts[] = $this->edition($reference, $terms);
            $parts[] = ItemType::Webpage === $reference->type ? $this->webSource($reference, $terms) : $this->publication($reference, $terms);
        }

        $parts[] = $this->link($reference);

        return $this->period($this->punctuated('.', $parts, $terms), $terms);
    }

    private function fullNote(Reference $reference, string $locator, Terms $terms): RichText
    {
        [$creators, $editors] = $this->creators($reference);
        $names = $this->noteNames($creators, $terms, false);

        if ($editors && '' !== $names) {
            $names .= ', '.(\count($creators) > 1 ? $terms->editors : $terms->editor);
        }

        $title = $this->noteTitle($reference, $terms);

        if ($this->isArticle($reference)) {
            $source = RichText::concat($this->periodical($reference, $terms), $this->articlePages($reference, $locator, $terms));

            return $this->punctuated(',', [$names, $title, $source, '' === $locator ? $this->link($reference) : ''], $terms);
        }

        if (ItemType::Webpage === $reference->type) {
            return $this->punctuated(',', [$names, $title, $this->webSource($reference, $terms), $this->link($reference), $locator], $terms);
        }

        if ($this->isInBook($reference)) {
            $title = RichText::concat($title->punctuate(',', $terms->closeQuote, $terms->punctuationInsideQuotes), ' ', $terms->in, ' ', RichText::italic((string) $reference->field('container-title')));
        }

        $publication = $this->publication($reference, $terms);
        $work = $this->punctuated(',', [$title, $this->abbreviatedEditors($reference, $terms, $editors), $this->edition($reference, $terms)], $terms);

        return $this->punctuated(',', [
            $names,
            RichText::join(' ', [$work, '' === $publication ? '' : '('.$publication.')']),
            $locator,
        ], $terms);
    }

    private function shortNote(Reference $reference, string $locator, Terms $terms): RichText
    {
        [$creators] = $this->creators($reference);
        $title = $this->shortTitle($reference);
        $short = '' === $title ? '' : ($reference->type->isStandalone() && ItemType::Webpage !== $reference->type ? RichText::italic($title) : $this->quoted($title, $terms));

        return $this->punctuated(',', [$this->noteNames($creators, $terms, true), $short, $locator], $terms);
    }

    private function noteTitle(Reference $reference, Terms $terms): RichText
    {
        return $this->title($reference, $terms, italic: $reference->type->isStandalone() && ItemType::Webpage !== $reference->type);
    }

    /**
     * @param list<Name> $names
     */
    private function noteNames(array $names, Terms $terms, bool $short): string
    {
        $written = array_map(fn (Name $name): string => $short ? $name->familyName() : $this->natural($name), $names);

        return \count($written) > 3 ? $written[0].' '.$terms->etAl : $this->listNames($written, $terms->and, $terms);
    }

    /**
     * @param list<Name> $names
     */
    private function entryNames(array $names, Terms $terms): string
    {
        $written = [];

        foreach ($names as $index => $name) {
            $written[] = 0 === $index ? $this->inverted($name) : $this->natural($name);
        }

        if (\count($written) > 10) {
            return implode(', ', \array_slice($written, 0, 7)).', '.$terms->etAl;
        }

        $last = array_pop($written);

        if (null === $last || [] === $written) {
            return (string) $last;
        }

        return implode(', ', $written).($terms->serialComma ? ', ' : ' ').$terms->and.' '.$last;
    }

    private function periodical(Reference $reference, Terms $terms): RichText
    {
        $volume = (string) $reference->field('volume');
        $issue = $reference->field('issue');

        return RichText::join(' ', [
            RichText::italic((string) $reference->field('container-title')),
            $volume.(null === $issue ? '' : ('' === $volume ? '' : ', ').$terms->issue.' '.$issue),
        ]);
    }

    private function articlePages(Reference $reference, string $locator, Terms $terms): string
    {
        $date = $reference->date(DateVariable::Issued)?->start;
        $year = $date instanceof PartialDate ? ' ('.$this->fullDate($date, $terms).')' : '';
        $pages = '' !== $locator ? $locator : $this->pages((string) $reference->field('page'));

        return $year.('' === $pages ? '' : rtrim($terms->colon).' '.$pages);
    }

    private function publication(Reference $reference, Terms $terms): string
    {
        $year = $this->issuedYear($reference, $terms);
        $place = (string) $reference->field('publisher-place');
        $publisher = (string) $reference->field('publisher');
        $where = '' !== $place && '' !== $publisher ? $place.$terms->colon.$publisher : $place.$publisher;

        return implode(', ', array_filter([$where, $year], static fn (string $part): bool => '' !== $part));
    }

    private function webSource(Reference $reference, Terms $terms): string
    {
        $date = $reference->date(DateVariable::Issued)?->start;

        return implode(', ', array_filter([
            (string) $reference->field('container-title'),
            $date instanceof PartialDate ? $this->fullDate($date, $terms) : '',
        ], static fn (string $part): bool => '' !== $part));
    }

    private function editedBy(Reference $reference, Terms $terms, bool $editorsAreCreators): string
    {
        $editors = $editorsAreCreators ? [] : $reference->names(NameVariable::Editor);

        return [] === $editors ? '' : $terms->editedBy.' '.$this->listNames(array_map(fn (Name $name): string => $this->natural($name), $editors), $terms->and, $terms);
    }

    private function abbreviatedEditors(Reference $reference, Terms $terms, bool $editorsAreCreators): string
    {
        $editors = $editorsAreCreators ? [] : $reference->names(NameVariable::Editor);

        return [] === $editors ? '' : $terms->editor.' '.$this->listNames(array_map(fn (Name $name): string => $this->natural($name), $editors), $terms->and, $terms);
    }
}
