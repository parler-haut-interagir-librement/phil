<?php

declare(strict_types=1);

namespace Phil\PrintPreflight\Check;

use Phil\PrintPreflight\Box;
use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\PlacedImage;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Rule;

/**
 * The images and elements drawn with transparency, when the printer's workflow does not accept it — one that asks for
 * a flattened file, as PDF/X-1a does: the file must be flattened before it is sent, and the printer's own flattening
 * may thicken text and lines near the transparent element.
 */
final readonly class Transparency implements Check
{
    public function check(Document $document, PrintProfile $profile): array
    {
        if ($profile->allowTransparency) {
            return [];
        }

        $findings = [];

        foreach ($document->pages as $index => $page) {
            $number = $index + 1;
            $transparent = [
                ...array_filter($page->images, static fn (PlacedImage $image): bool => $image->transparency),
                ...array_filter($page->boxes, static fn (Box $box): bool => $box->transparency),
            ];

            foreach ($transparent as $element) {
                $findings[] = Finding::of(Rule::TransparencyNotAllowed, \sprintf('"%s" on page %d uses transparency, which the printer does not accept; flatten it before the file is sent.', $element->id, $number), $number, $element->id);
            }
        }

        return $findings;
    }
}
