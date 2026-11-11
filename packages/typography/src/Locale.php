<?php

declare(strict_types=1);

namespace Phil\Typography;

use Phil\Typography\Exception\InvalidLocale;
use Stringable;

/**
 * A language, with an optional script and region, read from `fr`, `fr_CA`, `fr-ca` or `sr_Latn_RS`: what decides the
 * quotes, the spacing and the hyphenation patterns.
 */
final readonly class Locale implements Stringable
{
    private function __construct(
        public string $language,
        public ?string $script,
        public ?string $region,
    ) {
    }

    /**
     * @throws InvalidLocale
     */
    public static function parse(string $locale): self
    {
        if (1 !== preg_match('/^([a-z]{2,3})(?:[_-]([a-z]{4}))?(?:[_-]([a-z]{2}|\d{3}))?$/iD', $locale, $parts)) {
            throw InvalidLocale::of($locale);
        }

        return new self(
            strtolower($parts[1]),
            '' === ($parts[2] ?? '') ? null : ucfirst(strtolower($parts[2])),
            '' === ($parts[3] ?? '') ? null : strtoupper($parts[3]),
        );
    }

    /**
     * The tags to look up, from the most precise to the language alone: `sr_Latn_RS`, `sr_RS`, `sr_Latn`, `sr`.
     *
     * @return list<string>
     */
    public function candidates(): array
    {
        $candidates = [$this->__toString()];

        if (null !== $this->script && null !== $this->region) {
            $candidates[] = $this->language.'_'.$this->region;
            $candidates[] = $this->language.'_'.$this->script;
        }

        if (null !== $this->script || null !== $this->region) {
            $candidates[] = $this->language;
        }

        return $candidates;
    }

    public function __toString(): string
    {
        return implode('_', array_filter([$this->language, $this->script, $this->region], static fn (?string $part): bool => null !== $part));
    }
}
