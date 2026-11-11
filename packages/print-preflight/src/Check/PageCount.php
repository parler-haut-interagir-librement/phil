<?php

declare(strict_types=1);

namespace Phil\PrintPreflight\Check;

use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Rule;

/**
 * The number of pages against the printer's minimum, maximum and multiple: a saddle-stitched booklet prints in fours.
 */
final readonly class PageCount implements Check
{
    public function check(Document $document, PrintProfile $profile): array
    {
        $count = \count($document->pages);

        if (0 === $count) {
            return [Finding::of(Rule::PageCountEmpty, 'The document has no page.')];
        }

        $findings = [];

        if ($count < $profile->minimumPages) {
            $findings[] = Finding::of(Rule::PageCountBelowMinimum, \sprintf('The document has %s; the printer needs at least %d.', $this->pages($count), $profile->minimumPages), parameters: ['count' => $count, 'minimum' => $profile->minimumPages]);
        }

        if (null !== $profile->maximumPages && $count > $profile->maximumPages) {
            $findings[] = Finding::of(Rule::PageCountAboveMaximum, \sprintf('The document has %s; the printer takes at most %d.', $this->pages($count), $profile->maximumPages), parameters: ['count' => $count, 'maximum' => $profile->maximumPages]);
        }

        $remainder = $count % $profile->pageMultiple;

        if (0 !== $remainder) {
            $missing = $profile->pageMultiple - $remainder;
            $findings[] = Finding::of(Rule::PageCountMultiple, \sprintf('The document has %s, not a multiple of %d; add %s or remove %d.', $this->pages($count), $profile->pageMultiple, $this->pages($missing, 'blank page'), $remainder), parameters: ['count' => $count, 'multiple' => $profile->pageMultiple, 'missing' => $missing]);
        }

        return $findings;
    }

    private function pages(int $count, string $noun = 'page'): string
    {
        return \sprintf(1 === $count ? '%d %s' : '%d %ss', $count, $noun);
    }
}
