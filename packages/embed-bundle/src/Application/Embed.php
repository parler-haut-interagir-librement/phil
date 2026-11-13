<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Application;

use Phil\EmbedBundle\Domain\Exception\InvalidEmbed;
use Phil\EmbedBundle\Domain\ValueObject\EmbedClaims;
use Phil\EmbedBundle\Domain\ValueObject\Origin;

/**
 * A feature that other sites may embed, as the configuration declares it: its name, the origins allowed to embed it —
 * any origin a token names when the list is empty —, the themes it is shown in, the one it takes by default, and how
 * long a token issued for it holds.
 */
final readonly class Embed
{
    public const int MAX_TTL = 315_360_000;

    /**
     * @param list<Origin> $origins
     * @param list<string> $themes
     */
    private function __construct(
        public string $name,
        public array $origins,
        public array $themes,
        public ?string $defaultTheme,
        public ?int $ttl,
    ) {
    }

    /**
     * @param list<Origin> $origins
     * @param list<string> $themes
     *
     * @throws InvalidEmbed when the name or a theme is malformed, the default theme not one of the themes, or the
     *                      lifetime out of one second to ten years
     */
    public static function of(string $name, array $origins = [], array $themes = [], ?string $defaultTheme = null, ?int $ttl = null): self
    {
        if (1 !== preg_match(EmbedClaims::NAME, $name)) {
            throw InvalidEmbed::because(\sprintf('"%s" is not the name of an embed: a lowercase letter, then up to 63 lowercase letters, digits, "_" or "-".', $name));
        }

        foreach ($themes as $theme) {
            if (1 !== preg_match(EmbedClaims::THEME, $theme)) {
                throw InvalidEmbed::because(\sprintf('"%s" is not the name of a theme: a lowercase letter, then up to 31 lowercase letters, digits or "-".', $theme));
            }
        }

        if (null !== $defaultTheme && !\in_array($defaultTheme, $themes, true)) {
            throw InvalidEmbed::because(\sprintf('The default theme of the embed "%s" is one of its themes.', $name));
        }

        if (null !== $ttl && ($ttl < 1 || $ttl > self::MAX_TTL)) {
            throw InvalidEmbed::because(\sprintf('A token of the embed "%s" holds from one second to ten years.', $name));
        }

        return new self($name, $origins, array_values(array_unique($themes)), $defaultTheme, $ttl);
    }

    /**
     * Whether a site of that origin may embed it.
     */
    public function allows(Origin $origin): bool
    {
        return [] === $this->origins || array_any($this->origins, static fn (Origin $allowed): bool => $allowed->equals($origin));
    }

    /**
     * Whether it is shown in that theme.
     */
    public function hasTheme(string $theme): bool
    {
        return \in_array($theme, $this->themes, true);
    }
}
