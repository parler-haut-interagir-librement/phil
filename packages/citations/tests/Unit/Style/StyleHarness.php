<?php

declare(strict_types=1);

namespace Phil\Citations\Tests\Unit\Style;

use Phil\Citations\Cite;
use Phil\Citations\Model\Name;
use Phil\Citations\Model\Reference;
use Phil\Citations\RichText;
use Phil\Citations\Style\AbstractStyle;
use Phil\Citations\Style\BibliographyOrder;
use Phil\Citations\Style\Entry;
use Phil\Citations\Terms;

/**
 * A style that writes nothing and shows the helpers of the abstract one.
 */
final class StyleHarness extends AbstractStyle
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
        return RichText::empty();
    }

    public function entry(Entry $entry, Terms $terms): RichText
    {
        return RichText::empty();
    }

    public function writeNatural(Name $name, bool $initials = false): string
    {
        return $this->natural($name, $initials);
    }

    public function writeInverted(Name $name, bool $initials = false): string
    {
        return $this->inverted($name, $initials);
    }

    public function writeQuoted(string $text, Terms $terms, string $after = ''): string
    {
        return $this->quoted($text, $terms, $after)->toText();
    }

    public function writeTitle(Reference $reference, Terms $terms): string
    {
        return $this->title($reference, $terms)->toText();
    }

    public function writeShortTitle(Reference $reference): string
    {
        return $this->shortTitle($reference);
    }

    public function writeLocator(Cite $cite, Terms $terms): string
    {
        return $this->locator($cite, $terms);
    }

    public function writePages(string $pages): string
    {
        return $this->pages($pages);
    }
}
