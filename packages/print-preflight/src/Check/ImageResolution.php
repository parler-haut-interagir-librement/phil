<?php

declare(strict_types=1);

namespace Phil\PrintPreflight\Check;

use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\Length;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Rule;

/**
 * The effective resolution of each image at the size it is placed: below the printer's minimum it prints blurred,
 * below its target it may show, far above the maximum it only weighs on the file.
 */
final readonly class ImageResolution implements Check
{
    public function check(Document $document, PrintProfile $profile): array
    {
        $findings = [];

        foreach ($document->pages as $index => $page) {
            $number = $index + 1;

            foreach ($page->images as $image) {
                $resolution = $image->effectiveResolution();
                $shown = (int) floor($resolution);

                if ($resolution < $profile->minimumResolution) {
                    $findings[] = Finding::of(Rule::ImageBelowMinimum, \sprintf('The image "%s" on page %d prints at %d ppi, below the %s ppi minimum.', $image->id, $number, $shown, Length::number($profile->minimumResolution)), $number, $image->id, ['resolution' => $shown, 'minimum' => $profile->minimumResolution]);
                } elseif ($resolution < $profile->targetResolution) {
                    $findings[] = Finding::of(Rule::ImageBelowTarget, \sprintf('The image "%s" on page %d prints at %d ppi, below the %s ppi target.', $image->id, $number, $shown, Length::number($profile->targetResolution)), $number, $image->id, ['resolution' => $shown, 'target' => $profile->targetResolution]);
                } elseif (null !== $profile->maximumResolution && $resolution > $profile->maximumResolution) {
                    $findings[] = Finding::of(Rule::ImageAboveMaximum, \sprintf('The image "%s" on page %d prints at %d ppi, more than the %s ppi the printer uses.', $image->id, $number, $shown, Length::number($profile->maximumResolution)), $number, $image->id, ['resolution' => $shown, 'maximum' => $profile->maximumResolution]);
                }
            }
        }

        return $findings;
    }
}
