<?php

declare(strict_types=1);

namespace Phil\PrintPreflight;

use Phil\PrintPreflight\Exception\InvalidDocument;

/**
 * Any other element placed on a page — a block of text, a shape, a vector drawing, a background —, with the colour
 * space of its colours when it is known.
 *
 * An element that `bleeds` is meant to run off the page and must reach the bleed edge wherever it touches the trim
 * edge; any other element is content, which must stay inside the trim box and clear of its safety margin.
 *
 * What the application knows of how it prints, each left out when it does not: `inkCoverage`, the total ink coverage
 * of its flat colour in percent (the sum of its process inks, 400 for solid C, M, Y and K); `overprint`, whether it is
 * set to print over what lies beneath instead of knocking it out; `lineWidth`, the width of the thinnest line it
 * draws — a stroke, a rule, a border —, 0 for the thinnest the device draws; `transparency`, whether it is drawn with
 * transparency — an opacity below 1, a soft mask, a blend mode other than normal.
 */
final readonly class Box
{
    public function __construct(
        public string $id,
        public Rectangle $frame,
        public ?ColourSpace $colourSpace = null,
        public bool $bleeds = false,
        public ?float $inkCoverage = null,
        public bool $overprint = false,
        public ?Length $lineWidth = null,
        public bool $transparency = false,
    ) {
        if (null !== $inkCoverage && $inkCoverage < 0) {
            throw InvalidDocument::because(\sprintf('The ink coverage of "%s" cannot be negative; %s %% given.', $id, Length::number($inkCoverage)));
        }

        if ($lineWidth instanceof Length && $lineWidth->points < 0) {
            throw InvalidDocument::because(\sprintf('The line width of "%s" cannot be negative.', $id));
        }
    }
}
