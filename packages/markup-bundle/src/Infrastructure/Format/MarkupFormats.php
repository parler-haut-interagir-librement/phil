<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Infrastructure\Format;

use Phil\Markup\Level;
use Phil\Markup\MarkupFormat;

/**
 * The formats an application accepts, by name — the built-in ones and those add-ons bring —, the one taken when none is
 * named, the label of each format an add-on brings, and the levels: the default one, and the highest each format is
 * purified at — a format members write, as BBCode, kept below `all` whatever a template asks.
 */
final readonly class MarkupFormats
{
    /** The rank of each level in `Level::cases()`, from the strictest. */
    private const array RANKS = ['basic' => 0, 'forum' => 1, 'all' => 2];

    /**
     * @param list<string>          $enabled  the names of the formats accepted, in the order a form offers them
     * @param array<string, Level>  $ceilings the highest level of a format, by its name; `all` for a format not named
     * @param array<string, string> $labels   the label of each format an add-on brings, by its name
     */
    public function __construct(
        private array $enabled,
        private string $default,
        private Level $level = Level::All,
        private array $ceilings = [],
        private array $labels = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function enabled(): array
    {
        return $this->enabled;
    }

    public function isEnabled(MarkupFormat|string $format): bool
    {
        return \in_array(MarkupFormat::nameOf($format), $this->enabled, true);
    }

    /**
     * The name of a format, or of the default one for `null`.
     *
     * @throws UnavailableFormat when the format is unknown or not enabled
     */
    public function format(MarkupFormat|string|null $format = null): string
    {
        $name = null === $format ? $this->default : MarkupFormat::nameOf($format);

        return $this->isEnabled($name) ? $name : throw UnavailableFormat::named($name, $this->enabled);
    }

    /**
     * The label an add-on gives its format; `null` for a built-in format or a name no add-on brings.
     */
    public function label(string $format): ?string
    {
        return $this->labels[$format] ?? null;
    }

    /**
     * The level a text is purified at: the one asked, or the default one, lowered to the ceiling of its format when the
     * format is known. An unknown level is an error.
     */
    public function level(MarkupFormat|string|null $format, Level|string|null $level = null): Level
    {
        $asked = \is_string($level) ? Level::from($level) : $level ?? $this->level;
        $ceiling = null === $format ? Level::All : $this->ceiling($format);

        return Level::cases()[min(self::RANKS[$asked->value], self::RANKS[$ceiling->value])];
    }

    public function ceiling(MarkupFormat|string $format): Level
    {
        return $this->ceilings[MarkupFormat::nameOf($format)] ?? Level::All;
    }
}
