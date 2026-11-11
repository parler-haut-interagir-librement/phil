<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Application;

use Phil\QrCode\Exception\InvalidOption;
use Phil\QrCode\Logo;

/**
 * The logos of the configuration, by name, each with the share of the side its zone takes. A template names a logo;
 * it never gives a path, so no template reads a file the configuration did not list.
 *
 * Each file is read when it is first drawn and kept: it belongs to the configuration, the same for every request.
 */
final class Logos
{
    /** @var array<string, Logo> */
    private array $read = [];

    /**
     * @param array<string, array{path: string, zone: float}> $logos
     */
    public function __construct(
        private readonly array $logos = [],
    ) {
    }

    /**
     * The logo of a name, and the share of the side its zone takes.
     *
     * @return array{Logo, float}
     *
     * @throws InvalidOption when no logo has that name, or its file is not a logo
     */
    public function get(string $name): array
    {
        $logo = $this->logos[$name] ?? throw InvalidOption::of('logo', 'one of the configured logos ('.implode(', ', array_keys($this->logos)).')', '"'.$name.'"');

        return [$this->read[$name] ??= Logo::fromFile($logo['path']), $logo['zone']];
    }
}
