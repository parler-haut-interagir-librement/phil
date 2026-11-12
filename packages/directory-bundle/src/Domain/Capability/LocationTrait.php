<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Domain\Capability;

use Phil\Geo\Coordinates;

/**
 * @phpstan-require-implements LocationCapable
 */
trait LocationTrait
{
    private ?float $latitude = null;

    private ?float $longitude = null;

    public function getLocation(): ?Coordinates
    {
        return null === $this->latitude || null === $this->longitude ? null : Coordinates::of($this->latitude, $this->longitude);
    }

    public function setLocation(?Coordinates $location): void
    {
        $this->latitude = $location?->latitude;
        $this->longitude = $location?->longitude;
    }
}
