<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

use GdImage;
use Phil\PrintCompositor\Exception\InvalidLayout;
use Phil\PrintCompositor\Internal\Gd;

/**
 * Draws the print files of a job: for each ink, a transparent canvas at the zone's real size and the job's resolution,
 * on which the items of the layers of that ink are drawn, layer after layer and item after item.
 */
final readonly class PrintCompositor
{
    public function __construct(
        private TextFitter $fitter = new TextFitter(),
    ) {
    }

    public function compose(PrintJob $job): PrintFiles
    {
        [$width, $height] = $job->zone->pixelSize($job->dpi);
        $files = [];

        foreach ($job->inks() as $ink) {
            $canvas = Gd::canvas($width, $height);

            foreach ($job->layers as $layer) {
                if ($ink !== $layer->ink) {
                    continue;
                }

                foreach ($layer->items as $item) {
                    match (true) {
                        $item instanceof ImageItem => $this->drawImage($canvas, $item, $job->dpi),
                        $item instanceof TextItem => $this->drawText($canvas, $item, $job->dpi),
                        default => throw InvalidLayout::because(\sprintf('The item %s is neither an image nor a text: a layer draws ImageItem and TextItem values.', $item::class)),
                    };
                }
            }

            $files[$ink] = Raster::adopt($canvas);
        }

        \assert([] !== $files);

        return new PrintFiles($job, $files);
    }

    private function drawImage(GdImage $canvas, ImageItem $item, int $dpi): void
    {
        $box = $item->box->toPixels($dpi);
        $fitted = Gd::fitted($item->image->gdImage(), $box->width, $box->height, $item->fit, $item->horizontal, $item->vertical);
        imagecopy($canvas, $fitted, $box->x, $box->y, 0, 0, $box->width, $box->height);
    }

    private function drawText(GdImage $canvas, TextItem $item, int $dpi): void
    {
        $fitted = $this->fitter->fit($item, $dpi);
        $box = $item->box->toPixels($dpi);
        $top = $box->y + $item->vertical->offset($box->height, $fitted->height);
        $gdSize = TextFitter::gdSize($fitted->size, $dpi);
        $colour = Raster::gdColour($item->colour);

        foreach ($fitted->lines as $line) {
            imagettftext(
                $canvas,
                $gdSize,
                0,
                $box->x + $item->horizontal->offset($box->width, $line->width) - $line->left,
                $top + $line->baseline,
                $colour,
                $item->face->path,
                $line->text,
            );
        }
    }
}
