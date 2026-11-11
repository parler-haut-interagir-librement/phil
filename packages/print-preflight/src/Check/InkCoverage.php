<?php

declare(strict_types=1);

namespace Phil\PrintPreflight\Check;

use Phil\PrintPreflight\ColourWorkflow;
use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\Length;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Rule;

/**
 * The total ink coverage of each image and flat colour whose coverage the model gives, against the printer's maximum:
 * too much ink dries slowly, sets off on the facing sheet and may wrinkle thin paper. Checked in a CMYK workflow only;
 * in an RGB workflow the printer separates the colours and limits the ink itself.
 */
final readonly class InkCoverage implements Check
{
    public function check(Document $document, PrintProfile $profile): array
    {
        if (ColourWorkflow::Cmyk !== $profile->colourWorkflow) {
            return [];
        }

        $findings = [];
        $maximum = $profile->maximumInkCoverage;

        foreach ($document->pages as $index => $page) {
            $number = $index + 1;

            foreach ($page->images as $image) {
                if (null !== $image->inkCoverage && $image->inkCoverage > $maximum) {
                    $shown = (int) ceil($image->inkCoverage);
                    $findings[] = Finding::of(Rule::InkImageAboveMaximum, \sprintf('The image "%s" on page %d reaches %d %% of total ink coverage, above the %s %% the printer accepts.', $image->id, $number, $shown, Length::number($maximum)), $number, $image->id, ['coverage' => $shown, 'maximum' => $maximum]);
                }
            }

            foreach ($page->boxes as $box) {
                if (null !== $box->inkCoverage && $box->inkCoverage > $maximum) {
                    $shown = (int) ceil($box->inkCoverage);
                    $findings[] = Finding::of(Rule::InkFlatAboveMaximum, \sprintf('"%s" on page %d prints a flat colour of %d %% total ink coverage, above the %s %% the printer accepts.', $box->id, $number, $shown, Length::number($maximum)), $number, $box->id, ['coverage' => $shown, 'maximum' => $maximum]);
                }
            }
        }

        return $findings;
    }
}
