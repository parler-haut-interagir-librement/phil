<?php

declare(strict_types=1);

namespace Phil\Citations\Style;

use Phil\Citations\Model\DateVariable;
use Phil\Citations\Model\ItemType;
use Phil\Citations\Model\Name;
use Phil\Citations\Model\NameVariable;
use Phil\Citations\Model\PartialDate;
use Phil\Citations\RichText;
use Phil\Citations\Terms;

/**
 * An author-date style in the manner of APA: « (Smith & Jones, 2020, p. 12) » in the text, the works of one author
 * grouped (« (Smith, 2019, 2020a) »), and a bibliography in alphabetical order:
 *
 * « Smith, J. A., & Jones, B. (2020). Title of the article. *Journal*, *12*(3), 45–67. https://doi.org/… »
 *
 * One or two authors are named in a citation, three or more as the first and « et al. »; up to twenty in an entry,
 * then the first nineteen, an ellipsis and the last. Works without a date read « n.d. », works of the same authors
 * and year take a letter.
 */
final class AuthorDate extends AbstractStyle
{
    private const array DATED_TO_THE_DAY = [
        ItemType::ArticleMagazine, ItemType::ArticleNewspaper, ItemType::Post, ItemType::PostWeblog,
        ItemType::Webpage, ItemType::Broadcast, ItemType::Interview, ItemType::PersonalCommunication, ItemType::Speech,
    ];

    public function order(): BibliographyOrder
    {
        return BibliographyOrder::Alphabetical;
    }

    public function yearSuffixes(): bool
    {
        return true;
    }

    public function citation(array $items, Terms $terms): RichText
    {
        $groups = [];
        $previous = null;

        foreach ($items as $item) {
            $author = $this->citedAuthor($item->entry, $terms);
            $year = RichText::join(', ', [$this->year($item->entry, $terms), $this->locator($item->cite, $terms)]);
            $plain = null === $item->cite->prefix && null === $item->cite->suffix;

            if ($plain && null !== $previous && $previous[0]->toText() === $author->toText()) {
                $groups[\count($groups) - 1] = RichText::concat($groups[\count($groups) - 1], ', ', $year);

                continue;
            }

            $groups[] = $this->affixed($item->cite, RichText::join(', ', [$author, $year]));
            $previous = $plain ? [$author] : null;
        }

        return RichText::concat('(', RichText::join('; ', $groups), ')');
    }

    public function entry(Entry $entry, Terms $terms): RichText
    {
        $reference = $entry->reference;
        [$creators, $editors] = $this->creators($reference);
        $date = '('.$this->entryDate($entry, $terms).').';
        $title = $this->entryTitle($entry, $terms);

        $head = [] === $creators
            ? [$title, $date]
            : [RichText::text($this->authorList($creators, $editors, $terms))->sentence(), $date, $title];

        return RichText::join(' ', [...$head, $this->source($entry, $terms), $this->link($reference)]);
    }

    private function citedAuthor(Entry $entry, Terms $terms): RichText
    {
        [$creators] = $this->creators($entry->reference);
        $families = array_map(static fn (Name $name): string => $name->familyName(), $creators);

        return match (\count($families)) {
            0 => $this->title($entry->reference, $terms, italic: $entry->reference->type->isStandalone()),
            1 => RichText::text($families[0]),
            2 => RichText::text($families[0].' '.$terms->andSymbol.' '.$families[1]),
            default => RichText::text($families[0].' '.$terms->etAl),
        };
    }

    /**
     * @param list<Name> $names
     */
    private function authorList(array $names, bool $editors, Terms $terms): string
    {
        $inverted = array_map(fn (Name $name): string => $this->inverted($name, true), $names);

        if (\count($inverted) > 20) {
            $list = implode(', ', \array_slice($inverted, 0, 19)).', . . . '.end($inverted);
        } elseif (\count($inverted) > 1) {
            $last = array_pop($inverted);
            $list = implode(', ', $inverted).($terms->serialComma ? ', ' : ' ').$terms->andSymbol.' '.$last;
        } else {
            $list = $inverted[0];
        }

        if ($editors) {
            $list .= ' ('.$this->capitalized(\count($names) > 1 ? $terms->editors : $terms->editor, $terms).')';
        }

        return $list;
    }

    private function entryDate(Entry $entry, Terms $terms): string
    {
        $start = $entry->reference->date(DateVariable::Issued)?->start;
        $year = $this->year($entry, $terms);

        if (!$start instanceof PartialDate || null === $start->month || !\in_array($entry->reference->type, self::DATED_TO_THE_DAY, true)) {
            return $year;
        }

        $month = $terms->month($start->month);

        if (null === $start->day) {
            return $year.', '.$month;
        }

        return 'fr' === $terms->language ? $year.', '.$start->day.' '.$month : $year.', '.$month.' '.$start->day;
    }

    private function entryTitle(Entry $entry, Terms $terms): RichText
    {
        $reference = $entry->reference;
        $title = (string) $reference->title();

        if ('' === $title) {
            return RichText::empty();
        }

        $details = array_filter([
            $this->edition($reference, $terms),
            null === $reference->field('volume') || $this->isArticle($reference) ? '' : $this->capitalized($terms->volume, $terms).' '.$reference->field('volume'),
            ItemType::Report === $reference->type && null !== $reference->field('number') ? 'No. '.$reference->field('number') : '',
        ], static fn (string $detail): bool => '' !== $detail);

        $text = RichText::join(' ', [
            $reference->type->isStandalone() ? RichText::italic($title) : RichText::text($title),
            [] === $details ? '' : '('.implode(', ', $details).')',
            null === $reference->field('genre') ? '' : '['.$reference->field('genre').']',
        ]);

        return $text->sentence();
    }

    private function source(Entry $entry, Terms $terms): RichText
    {
        $reference = $entry->reference;
        $container = (string) $reference->field('container-title');
        $publisher = (string) $reference->field('publisher');

        if ($this->isArticle($reference)) {
            $volume = (string) $reference->field('volume');
            $issue = $reference->field('issue');

            return RichText::join(', ', [
                RichText::italic($container),
                RichText::concat(RichText::italic($volume), null === $issue ? '' : '('.$issue.')'),
                $this->pages((string) $reference->field('page')),
            ])->sentence();
        }

        if ($this->isInBook($reference)) {
            $editors = $reference->names(NameVariable::Editor);
            $editorText = [] === $editors ? '' : $this->listNames(
                array_map(fn (Name $name): string => $this->natural($name, true), $editors),
                $terms->andSymbol,
                $terms,
            ).' ('.$this->capitalized(\count($editors) > 1 ? $terms->editors : $terms->editor, $terms).')';
            $page = $reference->field('page');

            $in = RichText::join(' ', [
                ucfirst($terms->in),
                RichText::join(', ', [$editorText, RichText::italic($container)]),
                null === $page ? '' : '('.$terms->pages.' '.$this->pages($page).')',
            ]);

            return RichText::join(' ', [$in->sentence(), RichText::text($publisher)->sentence()]);
        }

        return RichText::text(ItemType::Webpage === $reference->type && '' !== $container ? $container : $publisher)->sentence();
    }
}
