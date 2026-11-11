<?php

declare(strict_types=1);

namespace Phil\PrintPreflight;

use Phil\PrintPreflight\Exception\InvalidProfile;

/**
 * What a printer asks of a file: the trim sizes it prints, the bleed and safety margin, the resolution of images, the
 * page count, the colour workflow; and how severe each rule is for it.
 *
 * Every argument has a default that suits most offset and digital printers: any trim size, one size for the whole
 * document, 3 mm of bleed, a 5 mm safety margin, images at 300 ppi with 150 ppi as the floor, at least one page, CMYK
 * without spot colours, 300 % of total ink coverage at most, lines of a quarter point at least, overprint and
 * transparency accepted.
 */
final readonly class PrintProfile
{
    public Length $bleed;

    public Length $safetyMargin;

    public Length $minimumLineWidth;

    /**
     * @param list<Size>              $trimSizes  the trim sizes the printer accepts, as many orientations as it accepts; empty for any
     * @param array<string, Severity> $severities the severity of a rule, by its code, in place of its default
     * @param list<string>            $ignore     the codes of the rules not to report at all
     */
    public function __construct(
        public array $trimSizes = [],
        public bool $uniformTrimSize = true,
        ?Length $bleed = null,
        ?Length $safetyMargin = null,
        public float $minimumResolution = 150,
        public float $targetResolution = 300,
        public ?float $maximumResolution = null,
        public int $minimumPages = 1,
        public ?int $maximumPages = null,
        public int $pageMultiple = 1,
        public ColourWorkflow $colourWorkflow = ColourWorkflow::Cmyk,
        public bool $allowSpotColours = false,
        public array $severities = [],
        public array $ignore = [],
        public float $maximumInkCoverage = 300,
        ?Length $minimumLineWidth = null,
        public bool $allowOverprint = true,
        public bool $allowTransparency = true,
    ) {
        $this->bleed = $bleed ?? Length::mm(3);
        $this->safetyMargin = $safetyMargin ?? Length::mm(5);
        $this->minimumLineWidth = $minimumLineWidth ?? Length::pt(0.25);

        if ($this->bleed->points < 0 || $this->safetyMargin->points < 0) {
            throw InvalidProfile::because('The bleed and the safety margin cannot be negative.');
        }

        if ($minimumResolution <= 0 || $targetResolution < $minimumResolution) {
            throw InvalidProfile::because(\sprintf('The minimum resolution must be positive and the target at least the minimum; %s and %s ppi given.', Length::number($minimumResolution), Length::number($targetResolution)));
        }

        if (null !== $maximumResolution && $maximumResolution < $targetResolution) {
            throw InvalidProfile::because(\sprintf('The maximum resolution cannot be below the target; %s and %s ppi given.', Length::number($maximumResolution), Length::number($targetResolution)));
        }

        if ($minimumPages < 1 || (null !== $maximumPages && $maximumPages < $minimumPages)) {
            throw InvalidProfile::because(\sprintf('The minimum page count must be at least 1 and the maximum at least the minimum; %d and %s given.', $minimumPages, $maximumPages ?? 'none'));
        }

        if ($pageMultiple < 1) {
            throw InvalidProfile::because(\sprintf('The page count must be a multiple of at least 1; %d given.', $pageMultiple));
        }

        if ($maximumInkCoverage <= 0) {
            throw InvalidProfile::because(\sprintf('The maximum ink coverage must be positive; %s %% given.', Length::number($maximumInkCoverage)));
        }

        if ($this->minimumLineWidth->points < 0) {
            throw InvalidProfile::because('The minimum line width cannot be negative.');
        }
    }

    /**
     * The severity a finding of this code has for the printer: the profile's own when it sets one, else the finding's.
     */
    public function severityOf(Finding $finding): Severity
    {
        return $this->severities[$finding->code] ?? $finding->severity;
    }

    public function ignores(Finding $finding): bool
    {
        return \in_array($finding->code, $this->ignore, true);
    }

    /**
     * Whether the printer accepts this trim size: any when it lists none.
     */
    public function accepts(Size $trim): bool
    {
        return [] === $this->trimSizes || array_any($this->trimSizes, static fn (Size $size): bool => $size->matches($trim));
    }
}
