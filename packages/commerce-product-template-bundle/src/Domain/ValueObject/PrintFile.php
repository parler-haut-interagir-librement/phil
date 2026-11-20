<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Domain\ValueObject;

/**
 * One print file of a product: the face and zone it is printed on, its ink, the zone's real size in millimetres and
 * resolution, and its path in the production store, named by its content.
 */
final readonly class PrintFile
{
    public function __construct(
        public string $face,
        public string $zone,
        public string $ink,
        public float $width,
        public float $height,
        public int $dpi,
        public string $path,
    ) {
    }

    /**
     * @return array{face: string, zone: string, ink: string, width: float, height: float, dpi: int, path: string}
     */
    public function toArray(): array
    {
        return ['face' => $this->face, 'zone' => $this->zone, 'ink' => $this->ink, 'width' => $this->width, 'height' => $this->height, 'dpi' => $this->dpi, 'path' => $this->path];
    }
}
