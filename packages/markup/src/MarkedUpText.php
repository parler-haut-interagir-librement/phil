<?php

declare(strict_types=1);

namespace Phil\Markup;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A text as it was written — its source and the name of its format — and the purified HTML compiled from it, kept so a
 * page is not compiled on every read. An entity embeds it: `#[ORM\Embedded(class: MarkedUpText::class)]`. The format
 * is stored by its name, so a format an add-on brings is stored as a built-in one; a name holds 16 characters at most.
 */
#[ORM\Embeddable]
final readonly class MarkedUpText
{
    /** The name of the format: the value of a `MarkupFormat`, or the name of a format an add-on brings. */
    #[ORM\Column(type: Types::STRING, length: 16)]
    public string $format;

    public function __construct(
        #[ORM\Column(type: Types::TEXT)]
        public string $source,
        MarkupFormat|string $format,
        #[ORM\Column(type: Types::TEXT)]
        public string $html,
    ) {
        $this->format = MarkupFormat::nameOf($format);
    }
}
