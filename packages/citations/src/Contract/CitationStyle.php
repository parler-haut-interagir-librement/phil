<?php

declare(strict_types=1);

namespace Phil\Citations\Contract;

use Phil\Citations\RichText;
use Phil\Citations\Style\BibliographyOrder;
use Phil\Citations\Style\CitedItem;
use Phil\Citations\Style\Entry;
use Phil\Citations\Terms;

/**
 * A citation style: how a citation reads in the text and how an entry reads in the bibliography. The processor
 * numbers the entries, orders them as the style asks, gives the year letters, and tells first cites from later ones;
 * the style only writes.
 *
 * The library holds three styles written in PHP; another one, such as a processor of CSL style files the application
 * provides, is a class that implements this interface.
 */
interface CitationStyle
{
    public function order(): BibliographyOrder;

    /**
     * Whether references of the same authors and year take a letter after the year (« 2020a », « 2020b »).
     */
    public function yearSuffixes(): bool;

    /**
     * A citation in the text: the parenthesis, the brackets, or the text of the note.
     *
     * @param non-empty-list<CitedItem> $items
     */
    public function citation(array $items, Terms $terms): RichText;

    /**
     * An entry of the bibliography.
     */
    public function entry(Entry $entry, Terms $terms): RichText;
}
