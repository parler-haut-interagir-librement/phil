<?php

declare(strict_types=1);

namespace Phil\PrintPreflight\Check;

use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Rule;

/**
 * The trim size of each page against the sizes the printer accepts and against the first page, and its bleed against
 * the bleed the printer asks for.
 */
final readonly class PageGeometry implements Check
{
    public function check(Document $document, PrintProfile $profile): array
    {
        if ([] === $document->pages) {
            return [];
        }

        $findings = [];
        $first = $document->pages[0]->trim;

        foreach ($document->pages as $index => $page) {
            $number = $index + 1;
            $size = $page->trim->describe();

            if (!$profile->accepts($page->trim)) {
                $findings[] = Finding::of(Rule::PageSizeNotAccepted, \sprintf('Page %d is %s, a trim size the printer does not print.', $number, $size), $number, parameters: ['size' => $size]);
            } elseif ($profile->uniformTrimSize && !$first->matches($page->trim)) {
                $findings[] = Finding::of(Rule::PageSizeMixed, \sprintf('Page %d is %s, unlike page 1 (%s).', $number, $size, $first->describe()), $number, parameters: ['size' => $size, 'first' => $first->describe()]);
            }

            if ($page->bleed->isShorterThan($profile->bleed)) {
                $findings[] = Finding::of(Rule::BleedTooSmall, \sprintf('Page %d has %s of bleed; the printer needs %s.', $number, $page->bleed->describe(), $profile->bleed->describe()), $number, parameters: ['bleed' => $page->bleed->describe(), 'required' => $profile->bleed->describe()]);
            }
        }

        return $findings;
    }
}
