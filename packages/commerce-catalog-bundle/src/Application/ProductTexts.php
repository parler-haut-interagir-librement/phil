<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Application;

use LogicException;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Model\ProductTranslation;
use Phil\DoctrineBehaviours\Translations;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\LocaleDimensions\FallbackChain;
use Phil\LocaleDimensions\Locale;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * The names of products in a locale: the translation of the first locale of its fallback chain that has one, read by
 * `phil/doctrine-behaviours`, or the product's own name when none has. A list is read in one query, never one per
 * product; a translation whose name is not written yet is skipped for the product's own.
 *
 * Without translations — no locale declared — every product is shown in its own name. The locale is the one asked,
 * or the request's, read each time from the translator, so a worker serves each request in its own.
 */
final readonly class ProductTexts
{
    public function __construct(
        private ?Translations $translations = null,
        private ?DeclaredLocales $locales = null,
        private ?LocaleAwareInterface $current = null,
    ) {
    }

    /**
     * The names of products in a locale — the request's when none is given.
     *
     * @param iterable<Product> $products
     */
    public function of(iterable $products, ?string $locale = null): ProductTextSet
    {
        $chain = $this->chain($locale ?? $this->current?->getLocale());

        if (!$chain instanceof FallbackChain || !$this->translations instanceof Translations) {
            return new ProductTextSet();
        }

        $list = [];

        foreach ($products as $product) {
            $list[] = $product;
        }

        $names = [];
        $set = $this->translations->preload($list, $chain);

        foreach ($list as $product) {
            $translation = $set->of($product);

            if ($translation instanceof ProductTranslation && '' !== $translation->name()) {
                $names[$product->sku] = $translation->name();
            }
        }

        return new ProductTextSet($names);
    }

    /**
     * The translation of a product's name in exactly a locale, to be written: the stored one, or a new, empty one
     * persisted for the next flush. Nothing is copied from another locale.
     */
    public function translate(Product $product, string $locale): ProductTranslation
    {
        $translation = $this->translations()->translate($product, Locale::fromString($locale));
        \assert($translation instanceof ProductTranslation);

        return $translation;
    }

    /**
     * The locales a product is translated in, for the alternates of its page.
     *
     * @return list<string>
     */
    public function locales(Product $product): array
    {
        return array_map(static fn (Locale $locale): string => $locale->value, $this->translations?->locales($product) ?? []);
    }

    private function translations(): Translations
    {
        return $this->translations ?? throw new LogicException('The products are translated with phil/doctrine-behaviours and phil_commerce_catalog.translations.locales: declare the locales served.');
    }

    /**
     * The chain of a locale the application serves; none for a locale it does not, or without declared locales.
     */
    private function chain(?string $locale): ?FallbackChain
    {
        if (null === $locale || !$this->locales instanceof DeclaredLocales) {
            return null;
        }

        try {
            $locale = Locale::fromString($locale);
        } catch (InvalidLocaleDimension) {
            return null;
        }

        return $this->locales->isServed($locale) ? $this->locales->chainFor($locale) : null;
    }
}
