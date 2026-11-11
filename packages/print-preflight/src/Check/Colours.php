<?php

declare(strict_types=1);

namespace Phil\PrintPreflight\Check;

use Phil\PrintPreflight\ColourSpace;
use Phil\PrintPreflight\ColourWorkflow;
use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Rule;

/**
 * The colour space of each image and element against the printer's workflow: RGB in a CMYK workflow, or the reverse,
 * is converted by the printer and may shift; a spot colour needs a plate the printer may not run.
 */
final readonly class Colours implements Check
{
    public function check(Document $document, PrintProfile $profile): array
    {
        $findings = [];

        foreach ($document->pages as $index => $page) {
            $number = $index + 1;

            foreach ($page->images as $image) {
                $finding = $this->finding($image->id, $image->colourSpace, $number, $profile);

                if ($finding instanceof Finding) {
                    $findings[] = $finding;
                }
            }

            foreach ($page->boxes as $box) {
                $finding = null === $box->colourSpace ? null : $this->finding($box->id, $box->colourSpace, $number, $profile);

                if ($finding instanceof Finding) {
                    $findings[] = $finding;
                }
            }
        }

        return $findings;
    }

    private function finding(string $id, ColourSpace $colourSpace, int $page, PrintProfile $profile): ?Finding
    {
        $workflow = $profile->colourWorkflow;

        return match (true) {
            ColourSpace::Rgb === $colourSpace && ColourWorkflow::Cmyk === $workflow => Finding::of(Rule::ColourRgbInCmyk, \sprintf('"%s" on page %d is in RGB; the printer converts it to CMYK, and bright colours may dull.', $id, $page), $page, $id),
            ColourSpace::Cmyk === $colourSpace && ColourWorkflow::Rgb === $workflow => Finding::of(Rule::ColourCmykInRgb, \sprintf('"%s" on page %d is in CMYK; the printer converts it from RGB with its own profile.', $id, $page), $page, $id),
            ColourSpace::Spot === $colourSpace && !$profile->allowSpotColours => Finding::of(Rule::ColourSpot, \sprintf('"%s" on page %d uses a spot colour; the printer converts it to process colours.', $id, $page), $page, $id),
            default => null,
        };
    }
}
