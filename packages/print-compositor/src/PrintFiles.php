<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

use Phil\PrintCompositor\Exception\InvalidLayout;

/**
 * The print files of a job, one per ink, each a transparent image at the zone's real size and the job's resolution.
 */
final readonly class PrintFiles
{
    /**
     * @param non-empty-array<string, Raster> $files by ink, in the order the inks first appear in the job
     */
    public function __construct(
        public PrintJob $job,
        public array $files,
    ) {
    }

    /**
     * @return non-empty-list<string>
     */
    public function inks(): array
    {
        return array_keys($this->files);
    }

    /**
     * The file of one ink, as an image: to save, or to place on a product photo for a mockup.
     */
    public function raster(string $ink = Layer::DEFAULT_INK): Raster
    {
        return $this->files[$ink] ?? throw InvalidLayout::because(\sprintf('The print job of the zone "%s" has no layer in the ink "%s"; its inks are "%s".', $this->job->zone->name, $ink, implode('", "', $this->inks())));
    }

    /**
     * The file of one ink as PNG bytes, with the job's resolution written in it.
     */
    public function png(string $ink = Layer::DEFAULT_INK): string
    {
        return $this->raster($ink)->png($this->job->dpi);
    }
}
