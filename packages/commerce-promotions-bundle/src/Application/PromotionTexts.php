<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Application;

use LogicException;
use Phil\CommercePromotionsBundle\Domain\Model\Promotion;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionTranslation;
use Phil\DoctrineBehaviours\Translations;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\LocaleDimensions\FallbackChain;
use Phil\LocaleDimensions\Locale;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * The labels of promotions in a locale: the translation of the first locale of its fallback chain that has one, read
 * by `phil/doctrine-behaviours`, or the promotion's own label when none has. A list is read in one query, never one
 * per promotion; a translation whose label is not written yet is skipped for the promotion's own.
 *
 * Without translations — no locale declared — every promotion is shown in its own label. The locale is the one asked,
 * or the request's, read each time from the translator, so a worker serves each request in its own.
 */
final readonly class PromotionTexts
{
    public function __construct(
        private ?Translations $translations = null,
        private ?DeclaredLocales $locales = null,
        private ?LocaleAwareInterface $current = null,
    ) {
    }

    /**
     * The labels of promotions in a locale — the request's when none is given.
     *
     * @param iterable<Promotion> $promotions
     */
    public function of(iterable $promotions, ?string $locale = null): PromotionTextSet
    {
        $chain = $this->chain($locale ?? $this->current?->getLocale());

        if (!$chain instanceof FallbackChain || !$this->translations instanceof Translations) {
            return new PromotionTextSet();
        }

        $list = [];

        foreach ($promotions as $promotion) {
            $list[] = $promotion;
        }

        $labels = [];
        $set = $this->translations->preload($list, $chain);

        foreach ($list as $promotion) {
            $translation = $set->of($promotion);

            if ($translation instanceof PromotionTranslation && '' !== $translation->label()) {
                $labels[$promotion->code] = $translation->label();
            }
        }

        return new PromotionTextSet($labels);
    }

    /**
     * The translation of a promotion's label in exactly a locale, to be written: the stored one, or a new, empty one
     * persisted for the next flush. Nothing is copied from another locale.
     */
    public function translate(Promotion $promotion, string $locale): PromotionTranslation
    {
        $translation = $this->translations()->translate($promotion, Locale::fromString($locale));
        \assert($translation instanceof PromotionTranslation);

        return $translation;
    }

    /**
     * The locales a promotion is translated in.
     *
     * @return list<string>
     */
    public function locales(Promotion $promotion): array
    {
        return array_map(static fn (Locale $locale): string => $locale->value, $this->translations?->locales($promotion) ?? []);
    }

    private function translations(): Translations
    {
        return $this->translations ?? throw new LogicException('The promotions are translated with phil/doctrine-behaviours and phil_commerce_promotions.translations.locales: declare the locales served.');
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
