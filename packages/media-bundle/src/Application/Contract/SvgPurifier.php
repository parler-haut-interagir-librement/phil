<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application\Contract;

use Phil\MediaBundle\Domain\Exception\InvalidMedia;

/**
 * Purifies and optimises an SVG image before the library keeps it: what could run, load or embed anything removed,
 * what changes nothing in the drawing dropped.
 */
interface SvgPurifier
{
    /**
     * @throws InvalidMedia when the image is refused whole
     */
    public function purify(string $svg): string;
}
