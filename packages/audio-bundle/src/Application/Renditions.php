<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Application;

use Phil\Audio\Rendition;
use Phil\AudioBundle\Application\Exception\UnknownRendition;

/**
 * The renditions the configuration names, each produced from every audio file uploaded.
 */
final readonly class Renditions
{
    /**
     * @param array<string, Rendition> $renditions by name
     */
    public function __construct(
        private array $renditions,
    ) {
    }

    /**
     * @throws UnknownRendition when the configuration does not name it
     */
    public function get(string $name): Rendition
    {
        return $this->renditions[$name] ?? throw new UnknownRendition(\sprintf('No rendition is named "%s".', $name));
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->renditions);
    }
}
