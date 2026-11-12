<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Dimension;

use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;

/**
 * What identifies one dimension row under its root: a locale, a stage and a version.
 *
 * **The unlocalized row has no locale.** It carries the values every locale shares — a template key, an
 * image, a date — so they are written once instead of once per language and cannot drift apart. Each
 * localized row is read over its unlocalized counterpart, the one with the same stage and version.
 */
final readonly class DimensionKey
{
    private function __construct(
        public ?Locale $locale,
        public Stage $stage,
        public Version $version,
    ) {
    }

    public static function unlocalized(Stage $stage, Version $version): self
    {
        return new self(null, $stage, $version);
    }

    public static function localized(Locale $locale, Stage $stage, Version $version): self
    {
        return new self($locale, $stage, $version);
    }

    public function isLocalized(): bool
    {
        return $this->locale instanceof Locale;
    }

    /**
     * The row whose shared values this one is read over. The unlocalized row is its own counterpart.
     */
    public function unlocalizedCounterpart(): self
    {
        return new self(null, $this->stage, $this->version);
    }

    /**
     * The same locale and version in another stage: publishing reads the draft key and writes this one.
     */
    public function inStage(Stage $stage): self
    {
        return new self($this->locale, $stage, $this->version);
    }

    /**
     * The same locale and stage in another version: editing a frozen version writes the next one.
     */
    public function atVersion(Version $version): self
    {
        return new self($this->locale, $this->stage, $version);
    }

    public function equals(self $other): bool
    {
        $sameLocale = $this->locale instanceof Locale
            ? $other->locale instanceof Locale && $this->locale->equals($other->locale)
            : !$other->locale instanceof Locale;

        return $sameLocale && $this->stage === $other->stage && $this->version->equals($other->version);
    }

    /**
     * A stable, readable form for messages and logs: `fr_CA/draft/3`, or `*` in place of the locale for
     * the unlocalized row.
     */
    public function toString(): string
    {
        return \sprintf('%s/%s/%d', $this->locale->value ?? '*', $this->stage->value, $this->version->number);
    }
}
