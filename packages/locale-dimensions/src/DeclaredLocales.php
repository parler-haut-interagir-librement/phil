<?php

declare(strict_types=1);

namespace Phil\LocaleDimensions;

use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Symfony\Component\Translation\LocaleFallbackProvider;

/**
 * The locales an application serves, each with its declared fallback chain.
 *
 * ```php
 * $locales = DeclaredLocales::fromArray([
 *     'fr_CA' => ['fr'],
 *     'fr' => [],
 *     'en_CA' => [],
 * ]);
 * ```
 *
 * Each key is a served locale and its value the locales tried after it, in order; a bundle reads the array from its
 * configuration. **A fallback must itself be served**: a chain that ends on a locale nobody declared would serve what
 * the application does not publish. A chain that names its own locale, or one locale twice, is refused by
 * `FallbackChain`.
 *
 * A locale whose value is `null` declares no chain and gets the default one: the parents Symfony's
 * `LocaleFallbackProvider` derives from ICU (`fr_CA → fr`, `en_CA → en`), then the default fallbacks, each kept only
 * when it is served. An empty list declares a chain with no fallback.
 */
final readonly class DeclaredLocales
{
    /**
     * @param array<string, FallbackChain> $chains by served locale, in declaration order
     */
    private function __construct(
        private array $chains,
    ) {
    }

    /**
     * @param array<array-key, mixed> $declared         served locale => list of its fallbacks, or `null` for the
     *                                                  default chain
     * @param array<array-key, mixed> $defaultFallbacks the served locales that end every default chain, in order
     *
     * @throws InvalidLocaleDimension when a locale is malformed, a fallback is not served, or a chain repeats a locale
     */
    public static function fromArray(array $declared, array $defaultFallbacks = []): self
    {
        $served = [];
        $entries = [];

        foreach ($declared as $key => $fallbacks) {
            $locale = Locale::fromString((string) $key);
            $served[$locale->value] = $locale;
            $entries[] = [$locale, $fallbacks];
        }

        $defaults = [];

        foreach ($defaultFallbacks as $fallback) {
            if (!\is_string($fallback) || !isset($served[$fallback])) {
                throw InvalidLocaleDimension::because(\sprintf('The default fallback "%s" is not a served locale.', \is_string($fallback) ? $fallback : get_debug_type($fallback)));
            }

            $defaults[] = $fallback;
        }

        $provider = new LocaleFallbackProvider($defaults);
        $chains = [];

        foreach ($entries as [$locale, $fallbacks]) {
            $fallbacks ??= array_filter($provider->computeFallbackLocales($locale->value), static fn (string $fallback): bool => isset($served[$fallback]));

            if (!\is_array($fallbacks)) {
                throw InvalidLocaleDimension::because(\sprintf('The fallbacks of "%s" are not a list of locales.', $locale->value));
            }

            $chain = [$locale];

            foreach ($fallbacks as $fallback) {
                if (!\is_string($fallback) || !isset($served[$fallback])) {
                    throw InvalidLocaleDimension::because(\sprintf('The fallback "%s" of "%s" is not a served locale.', \is_string($fallback) ? $fallback : get_debug_type($fallback), $locale->value));
                }

                $chain[] = $served[$fallback];
            }

            $chains[$locale->value] = FallbackChain::of($chain);
        }

        return new self($chains);
    }

    /**
     * @return list<Locale>
     */
    public function all(): array
    {
        return array_values(array_map(static fn (FallbackChain $chain): Locale => $chain->requested(), $this->chains));
    }

    public function isServed(Locale $locale): bool
    {
        return isset($this->chains[$locale->value]);
    }

    /**
     * @throws InvalidLocaleDimension when the locale is not served
     */
    public function chainFor(Locale $locale): FallbackChain
    {
        return $this->chains[$locale->value]
            ?? throw InvalidLocaleDimension::because(\sprintf('The locale "%s" is not served.', $locale->value));
    }
}
