<?php

declare(strict_types=1);

namespace Phil\Fonts;

use Phil\Fonts\Licence\Licence;
use Phil\Fonts\Name\NameId;
use Phil\Fonts\Name\Names;

/**
 * The metadata of one face: its names, its weight, width and slope, its embedding permissions, its licence, its
 * variation axes and the characters it covers.
 */
final readonly class Font
{
    /** The width each `usWidthClass` from 1 to 9 stands for, as a percentage of the normal width. */
    private const array WIDTHS = [1 => 50.0, 2 => 62.5, 3 => 75.0, 4 => 87.5, 5 => 100.0, 6 => 112.5, 7 => 125.0, 8 => 150.0, 9 => 200.0];

    /**
     * @param int<1, 1000>          $weight        the `usWeightClass`, 400 regular and 700 bold
     * @param int<1, 9>             $widthClass    the `usWidthClass`, 5 normal
     * @param list<Axis>            $axes          the variation axes, none for a static font
     * @param list<array{int, int}> $unicodeRanges the first and last code point of each run the character map covers
     */
    public function __construct(
        public Names $names = new Names(),
        public int $weight = 400,
        public int $widthClass = 5,
        public FontStyle $style = FontStyle::Normal,
        public EmbeddingPermissions $embedding = new EmbeddingPermissions(),
        public Outlines $outlines = Outlines::TrueType,
        public array $axes = [],
        public array $unicodeRanges = [],
        public float $italicAngle = 0.0,
        public bool $fixedPitch = false,
    ) {
    }

    /**
     * The family the face belongs to: the typographic family when the font gives one, as CSS expects, else the
     * family of the four-style model; an empty string when the font names none.
     */
    public function family(): string
    {
        return $this->names->get(NameId::TypographicFamily) ?? $this->names->get(NameId::Family) ?? '';
    }

    /**
     * The face within its family, typographic first: "Bold Italic", "Condensed Light".
     */
    public function subfamily(): string
    {
        return $this->names->get(NameId::TypographicSubfamily) ?? $this->names->get(NameId::Subfamily) ?? '';
    }

    public function fullName(): ?string
    {
        return $this->names->get(NameId::FullName);
    }

    public function postScriptName(): ?string
    {
        return $this->names->get(NameId::PostScriptName);
    }

    public function version(): ?string
    {
        return $this->names->get(NameId::Version);
    }

    public function copyright(): ?string
    {
        return $this->names->get(NameId::Copyright);
    }

    public function trademark(): ?string
    {
        return $this->names->get(NameId::Trademark);
    }

    public function manufacturer(): ?string
    {
        return $this->names->get(NameId::Manufacturer);
    }

    public function designer(): ?string
    {
        return $this->names->get(NameId::Designer);
    }

    public function vendorUrl(): ?string
    {
        return $this->names->get(NameId::VendorUrl);
    }

    public function designerUrl(): ?string
    {
        return $this->names->get(NameId::DesignerUrl);
    }

    public function licence(): Licence
    {
        return new Licence($this->names->get(NameId::LicenceDescription), $this->names->get(NameId::LicenceUrl));
    }

    public function isVariable(): bool
    {
        return [] !== $this->axes;
    }

    public function axis(string $tag): ?Axis
    {
        return array_find($this->axes, static fn (Axis $axis): bool => $tag === $axis->tag);
    }

    /**
     * The weights the face draws: the range of its `wght` axis, rounded, or its weight class twice.
     *
     * @return array{int, int}
     */
    public function weightRange(): array
    {
        $axis = $this->axis('wght');

        return $axis instanceof Axis ? [(int) round($axis->minimum), (int) round($axis->maximum)] : [$this->weight, $this->weight];
    }

    /**
     * The widths the face draws, in percent of the normal width: the range of its `wdth` axis, or the width its
     * width class stands for, twice.
     *
     * @return array{float, float}
     */
    public function stretchRange(): array
    {
        $axis = $this->axis('wdth');

        return $axis instanceof Axis ? [$axis->minimum, $axis->maximum] : [self::WIDTHS[$this->widthClass], self::WIDTHS[$this->widthClass]];
    }
}
