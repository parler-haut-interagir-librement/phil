<?php

declare(strict_types=1);

namespace Phil\MapBundle\Domain\ValueObject;

use Phil\Geo\Coordinates;
use Phil\MapBundle\Domain\Exception\InvalidMap;

/**
 * A place shown by a marker and listed under the map: its identifier, its name, where it is — phil/geo's coordinates,
 * which a geocoder or a stored position gives —, and optionally a line about it and the page it links to.
 *
 * The name and the description are text, escaped where they are written: never HTML.
 */
final readonly class MapPlace
{
    public const int MAX_NAME = 200;

    public const int MAX_DESCRIPTION = 500;

    private function __construct(
        public string $id,
        public string $name,
        public Coordinates $coordinates,
        public ?string $description,
        public ?SiteAddress $link,
    ) {
    }

    /**
     * @throws InvalidMap when the identifier or the name is empty, or a text longer than its limit
     */
    public static function of(string $id, string $name, Coordinates $coordinates, ?string $description = null, ?SiteAddress $link = null): self
    {
        if ('' === trim($id) || \strlen($id) > self::MAX_NAME) {
            throw InvalidMap::because(\sprintf('A place has an identifier of 1 to %d bytes.', self::MAX_NAME));
        }

        if ('' === trim($name) || mb_strlen($name) > self::MAX_NAME) {
            throw InvalidMap::because(\sprintf('A place has a name of 1 to %d characters.', self::MAX_NAME));
        }

        if (null !== $description && mb_strlen($description) > self::MAX_DESCRIPTION) {
            throw InvalidMap::because(\sprintf('The description of a place holds %d characters at most.', self::MAX_DESCRIPTION));
        }

        return new self($id, $name, $coordinates, '' === $description ? null : $description, $link);
    }
}
