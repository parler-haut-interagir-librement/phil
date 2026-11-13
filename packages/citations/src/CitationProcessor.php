<?php

declare(strict_types=1);

namespace Phil\Citations;

use Collator;
use Phil\Citations\Contract\CitationStyle;
use Phil\Citations\Exception\UnknownReference;
use Phil\Citations\Internal\Creators;
use Phil\Citations\Model\DateVariable;
use Phil\Citations\Model\Name;
use Phil\Citations\Model\Reference;
use Phil\Citations\Style\BibliographyOrder;
use Phil\Citations\Style\CitedItem;
use Phil\Citations\Style\Entry;

/**
 * The citations and the bibliography of one text in one style and one language. It reads the citations in their
 * order, resolves each id through the reference list — a duplicate to the reference it duplicates —, numbers the
 * entries and orders them as the style asks, gives the year letters, tells a first cite from a later one, and leaves
 * the writing to the style.
 *
 * It keeps nothing between two texts: one instance can serve every request of a worker.
 */
final readonly class CitationProcessor
{
    public function __construct(
        private CitationStyle $style,
        private Terms $terms,
    ) {
    }

    /**
     * @param iterable<Citation> $citations the citations of the text, in their order
     * @param bool               $uncited   list in the bibliography the references never cited too
     *
     * @throws UnknownReference a citation of an id the list does not hold
     */
    public function render(ReferenceList $references, iterable $citations, bool $uncited = false): RenderedCitations
    {
        $resolved = [];
        $cited = [];

        foreach ($citations as $citation) {
            $items = [];

            foreach ($citation->cites as $cite) {
                $reference = $references->get($cite->id);
                $items[$reference->id] ??= [$reference, $cite];
                $cited[$reference->id] = $reference;
            }

            $resolved[] = $items;
        }

        $listed = $cited;

        if ($uncited) {
            foreach ($references->all() as $reference) {
                $listed[$reference->id] = $reference;
            }
        }

        $entries = $this->entries(array_values($listed));
        $seen = [];
        $rendered = [];

        foreach ($resolved as $items) {
            $cites = [];

            foreach ($items as [$reference, $cite]) {
                $cites[] = new CitedItem($entries[$reference->id], $cite, !isset($seen[$reference->id]));
            }

            foreach ($items as [$reference]) {
                $seen[$reference->id] = true;
            }

            $rendered[] = $this->style->citation($cites, $this->terms);
        }

        return new RenderedCitations(
            $rendered,
            array_values(array_map(fn (Entry $entry): RichText => $this->style->entry($entry, $this->terms), $entries)),
        );
    }

    /**
     * @param list<Reference> $references in the order of first citation
     *
     * @return array<string, Entry> in the order of the bibliography
     */
    private function entries(array $references): array
    {
        if (BibliographyOrder::Alphabetical === $this->style->order()) {
            $collator = new Collator($this->terms->language);
            usort($references, fn (Reference $a, Reference $b): int => $this->compare($collator, $a, $b));
        }

        $suffixes = $this->style->yearSuffixes() ? $this->yearSuffixes($references) : [];
        $entries = [];

        foreach ($references as $index => $reference) {
            $entries[$reference->id] = new Entry($reference, $index + 1, $suffixes[$reference->id] ?? '');
        }

        return $entries;
    }

    private function compare(Collator $collator, Reference $a, Reference $b): int
    {
        $order = [
            (int) $collator->compare($this->creatorKey($a), $this->creatorKey($b)),
            $this->year($a) <=> $this->year($b),
            (int) $collator->compare((string) $a->title(), (string) $b->title()),
        ];

        foreach ($order as $comparison) {
            if (0 !== $comparison) {
                return $comparison;
            }
        }

        return strcmp($a->id, $b->id);
    }

    /**
     * The letters of references of the same creators and year, in the order of the bibliography.
     *
     * @param list<Reference> $references
     *
     * @return array<string, string>
     */
    private function yearSuffixes(array $references): array
    {
        $groups = [];

        foreach ($references as $reference) {
            $groups[$this->creatorKey($reference).'|'.$this->year($reference)][] = $reference->id;
        }

        $suffixes = [];

        foreach ($groups as $ids) {
            if (\count($ids) < 2) {
                continue;
            }

            $letter = 'a';

            foreach ($ids as $id) {
                $suffixes[$id] = $letter;
                $letter = str_increment($letter);
            }
        }

        return $suffixes;
    }

    private function creatorKey(Reference $reference): string
    {
        $names = Creators::of($reference);

        if ([] === $names) {
            return (string) $reference->title();
        }

        return implode(' ; ', array_map(static fn (Name $name): string => $name->familyName().' '.$name->given, $names));
    }

    private function year(Reference $reference): int
    {
        return $reference->date(DateVariable::Issued)?->startYear() ?? \PHP_INT_MAX;
    }
}
