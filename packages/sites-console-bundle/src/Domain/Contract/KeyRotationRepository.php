<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Contract;

use Phil\SitesConsoleBundle\Domain\Model\KeyRotation;

/**
 * The rotations of the console key and where each stands on each site.
 */
interface KeyRotationRepository
{
    public function get(string $id): ?KeyRotation;

    public function save(KeyRotation $rotation): void;

    /**
     * The rotation not finished yet, if any.
     */
    public function unfinished(): ?KeyRotation;

    /**
     * The newest rotation to the given public key, in base64url.
     */
    public function latestTo(string $nextKey): ?KeyRotation;
}
