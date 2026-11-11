<?php

declare(strict_types=1);

namespace Phil\PrintPreflight\Check;

use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Rule;

/**
 * The lines each element draws, when the model gives their width, against the thinnest line the printer reproduces:
 * a thinner line — a hairline of width 0 among them — may break up or vanish on the plate.
 */
final readonly class Hairlines implements Check
{
    public function check(Document $document, PrintProfile $profile): array
    {
        $findings = [];
        $minimum = $profile->minimumLineWidth;

        foreach ($document->pages as $index => $page) {
            $number = $index + 1;

            foreach ($page->boxes as $box) {
                if (null !== $box->lineWidth && $box->lineWidth->points < $minimum->points) {
                    $findings[] = Finding::of(Rule::LineHairline, \sprintf('"%s" on page %d draws lines %s thick, thinner than the %s the printer reproduces; they may break up or vanish.', $box->id, $number, $box->lineWidth->describeInPoints(), $minimum->describeInPoints()), $number, $box->id, ['width' => $box->lineWidth->describeInPoints(), 'minimum' => $minimum->describeInPoints()]);
                }
            }
        }

        return $findings;
    }
}
