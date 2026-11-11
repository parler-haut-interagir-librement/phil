<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Infrastructure\PagedMedia;

use Phil\PagedMedia\Image\Image;
use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Document;
use Phil\QrCode\Contract\Content;
use Phil\QrCode\Exception\InvalidOption;
use Phil\QrCode\Zone;
use Phil\QrCodeBundle\Application\QrCodes;

/**
 * QR codes drawn in a PDF document of phil/paged-media, in vectors: a light square with the quiet zone, and each run of
 * dark modules of a row as one rectangle, so the code stays sharp at any print size. A PNG or JPEG logo is placed in
 * the zone the code keeps for it.
 */
final readonly class PdfQrCodes
{
    public function __construct(
        private QrCodes $codes,
    ) {
    }

    /**
     * Draws the code on the current page, its bottom left corner at (x, y), `$side` points wide, quiet zone included.
     *
     * @param string|Content|array<array-key, mixed> $content
     * @param array<string, mixed>                   $options the options of `QrCodes`
     *
     * @throws InvalidOption when the logo is an SVG, which a PDF does not place
     */
    public function draw(Document $document, string|array|Content $content, float $x, float $y, float $side, array $options = []): void
    {
        $code = $this->codes->encode($content, $options);
        $drawing = $this->codes->drawing($options);
        $quiet = $drawing['quiet_zone'];
        $module = $side / ($code->size() + 2 * $quiet);
        $dark = Color::hex($drawing['dark']);
        $zone = $code->zone;
        $document->rectangle($x, $y, $side, $side, fill: Color::hex($drawing['light']));

        foreach ($code->rows() as $row => $modules) {
            if ($zone instanceof Zone && $row >= $zone->y && $row < $zone->y + $zone->side) {
                $modules = substr_replace($modules, str_repeat('0', $zone->side), $zone->x, $zone->side);
            }

            preg_match_all('/1+/', $modules, $runs, \PREG_OFFSET_CAPTURE);
            $top = $y + $side - ($row + $quiet + 1) * $module;

            foreach ($runs[0] as [$run, $column]) {
                $document->rectangle($x + ($column + $quiet) * $module, $top, \strlen($run) * $module, $module, fill: $dark);
            }
        }

        $logo = $drawing['logo'];

        if (null === $logo) {
            return;
        }

        if (!$zone instanceof Zone) {
            throw InvalidOption::of('code of a logo', 'encoded with a zone for it', 'a code without one');
        }

        if ('image/svg+xml' === $logo->mediaType) {
            throw InvalidOption::of('logo of a PDF', 'a PNG or JPEG image', 'an SVG');
        }

        // The logo fitted half a module inside the zone, centred, its proportions kept.
        $image = Image::fromBytes($logo->bytes);
        $box = ($zone->side - 1) * $module;
        $scale = $box / max($image->width, $image->height);
        $width = $image->width * $scale;
        $height = $image->height * $scale;
        $centreX = $x + ($zone->x + $quiet + $zone->side / 2) * $module;
        $centreY = $y + $side - ($zone->y + $quiet + $zone->side / 2) * $module;
        $document->image($image, $centreX - $width / 2, $centreY - $height / 2, $width, $height);
    }
}
