<?php

declare(strict_types=1);

namespace Phil\PrintPreflight\Check;

use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Rule;

/**
 * The elements set to overprint: when the printer honours overprint, a white element set to overprint lays no ink and
 * vanishes; when it does not, every element set to overprint knocks out what lies beneath, unlike the proof.
 */
final readonly class Overprint implements Check
{
    /** Below this total ink coverage, in percent, an element prints as the paper: white. */
    public const float NO_INK = 0.5;

    public function check(Document $document, PrintProfile $profile): array
    {
        $findings = [];

        foreach ($document->pages as $index => $page) {
            $number = $index + 1;

            foreach ($page->boxes as $box) {
                if (!$box->overprint) {
                    continue;
                }

                if (!$profile->allowOverprint) {
                    $findings[] = Finding::of(Rule::OverprintNotAllowed, \sprintf('"%s" on page %d is set to overprint, which the printer ignores: it will knock out what lies beneath.', $box->id, $number), $number, $box->id);
                } elseif (null !== $box->inkCoverage && $box->inkCoverage < self::NO_INK) {
                    $findings[] = Finding::of(Rule::OverprintWhite, \sprintf('"%s" on page %d is white and set to overprint: it will not print at all.', $box->id, $number), $number, $box->id);
                }
            }
        }

        return $findings;
    }
}
