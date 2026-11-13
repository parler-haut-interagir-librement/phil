<?php

declare(strict_types=1);

namespace Phil\Citations\Style;

use Phil\Citations\Model\DateVariable;
use Phil\Citations\Model\ItemType;
use Phil\Citations\Model\Name;
use Phil\Citations\Model\NameVariable;
use Phil\Citations\Model\PartialDate;
use Phil\Citations\Model\Reference;
use Phil\Citations\RichText;
use Phil\Citations\Terms;

/**
 * A numeric style in the manner of IEEE: references numbered in the order they are first cited, « [1] », « [1], [4] »,
 * « [2]–[5] » for three or more in a row, « [3, p. 12] » with a locator, and a bibliography in the same order:
 *
 * « [1] J. A. Smith and B. Jones, “Title of the article,” *Journal*, vol. 12, no. 3, pp. 45–67, May 2020, doi: … »
 *
 * An entry names up to six authors, more as the first and « et al. ». The title of a web page is quoted.
 */
final class Numeric extends AbstractStyle
{
    public function order(): BibliographyOrder
    {
        return BibliographyOrder::Citation;
    }

    public function yearSuffixes(): bool
    {
        return false;
    }

    public function citation(array $items, Terms $terms): RichText
    {
        usort($items, static fn (CitedItem $a, CitedItem $b): int => $a->entry->number <=> $b->entry->number);
        $parts = [];
        $run = [];

        foreach ($items as $item) {
            $number = $item->entry->number;
            $locator = $this->locator($item->cite, $terms);
            $plain = '' === $locator && null === $item->cite->prefix && null === $item->cite->suffix;

            if ($plain && [] !== $run && end($run) + 1 === $number) {
                $run[] = $number;

                continue;
            }

            array_push($parts, ...$this->run($run));
            $run = $plain ? [$number] : [];

            if (!$plain) {
                $parts[] = $this->affixed($item->cite, RichText::text('['.implode(', ', array_filter([(string) $number, $locator], static fn (string $part): bool => '' !== $part)).']'))->toText();
            }
        }

        array_push($parts, ...$this->run($run));

        return RichText::text(implode(', ', $parts));
    }

    public function entry(Entry $entry, Terms $terms): RichText
    {
        $reference = $entry->reference;
        [$creators, $editors] = $this->creators($reference);
        $names = $this->names($creators, $terms);

        if ($editors && '' !== $names) {
            $names .= ', '.$this->capitalized(\count($creators) > 1 ? $terms->editors : $terms->editor, $terms);
        }

        $body = match (true) {
            $this->isArticle($reference) => $this->article($reference, $names, $terms),
            $this->isInBook($reference) => $this->inBook($reference, $names, $terms),
            ItemType::Webpage === $reference->type => $this->webpage($reference, $names, $terms),
            default => $this->book($reference, $names, $terms),
        };

        return RichText::concat('['.$entry->number.'] ', $body);
    }

    /**
     * @param list<int> $run
     *
     * @return list<string>
     */
    private function run(array $run): array
    {
        if (\count($run) >= 3) {
            return ['['.$run[0].']–['.end($run).']'];
        }

        return array_map(static fn (int $number): string => '['.$number.']', $run);
    }

    /**
     * @param list<Name> $names
     */
    private function names(array $names, Terms $terms): string
    {
        $written = array_map(fn (Name $name): string => $this->natural($name, true), $names);

        return \count($written) > 6 ? $written[0].' '.$terms->etAl : $this->listNames($written, $terms->and, $terms);
    }

    private function article(Reference $reference, string $names, Terms $terms): RichText
    {
        $date = $reference->date(DateVariable::Issued)?->start;
        $doi = $reference->field('DOI');
        $page = $reference->field('page');

        return $this->period($this->punctuated(',', [
            $names,
            $this->title($reference, $terms),
            RichText::italic((string) $reference->field('container-title')),
            null === $reference->field('volume') ? '' : $terms->volume.' '.$reference->field('volume'),
            null === $reference->field('issue') ? '' : $terms->issue.' '.$reference->field('issue'),
            null === $page ? '' : $terms->pages.' '.$this->pages($page),
            $date instanceof PartialDate ? $this->monthYear($date->month, $date->year, $terms) : '',
            null === $doi ? (string) $reference->field('URL') : 'doi'.$terms->colon.$doi,
        ], $terms), $terms);
    }

    private function inBook(Reference $reference, string $names, Terms $terms): RichText
    {
        $editors = $reference->names(NameVariable::Editor);
        $editorText = [] === $editors ? '' : $this->listNames(array_map(fn (Name $name): string => $this->natural($name, true), $editors), $terms->and, $terms)
            .', '.$this->capitalized(\count($editors) > 1 ? $terms->editors : $terms->editor, $terms);
        $page = $reference->field('page');

        $head = $this->punctuated(',', [
            $names,
            $this->title($reference, $terms),
            RichText::concat($terms->in.' ', RichText::italic((string) $reference->field('container-title'))),
            $editorText,
        ], $terms);

        return $this->period($this->punctuated(',', [
            $this->punctuated('.', [$head, $this->placePublisher($reference, $terms)], $terms),
            $this->issuedYear($reference, $terms),
            null === $page ? '' : $terms->pages.' '.$this->pages($page),
        ], $terms), $terms);
    }

    private function book(Reference $reference, string $names, Terms $terms): RichText
    {
        $head = $this->punctuated(',', [$names, $this->title($reference, $terms), $this->edition($reference, $terms)], $terms);

        return $this->period($this->punctuated(',', [
            $this->punctuated('.', [$head, $this->placePublisher($reference, $terms)], $terms),
            $this->issuedYear($reference, $terms),
        ], $terms), $terms);
    }

    private function webpage(Reference $reference, string $names, Terms $terms): RichText
    {
        $accessed = $reference->date(DateVariable::Accessed)?->start;
        $url = (string) $reference->field('URL');

        $text = $this->punctuated('.', [
            $this->punctuated(',', [$names, $this->title($reference, $terms, italic: false), (string) $reference->field('container-title')], $terms),
            $accessed instanceof PartialDate ? ucfirst($terms->accessed).rtrim($terms->colon).' '.$this->fullDate($accessed, $terms) : '',
            '' === $url ? '' : '['.$terms->online.']',
        ], $terms);

        return '' === $url ? $this->period($text, $terms) : $this->punctuated('.', [$text, $terms->available.rtrim($terms->colon).' '.$url], $terms);
    }

    private function placePublisher(Reference $reference, Terms $terms): string
    {
        $place = (string) $reference->field('publisher-place');
        $publisher = (string) $reference->field('publisher');

        return '' !== $place && '' !== $publisher ? $place.$terms->colon.$publisher : $place.$publisher;
    }

    private function monthYear(?int $month, int $year, Terms $terms): string
    {
        if (null === $month) {
            return (string) $year;
        }

        $name = $terms->month($month);

        return ('en' === $terms->language && mb_strlen($name) > 4 ? mb_substr($name, 0, 3).'.' : $name).' '.$year;
    }
}
