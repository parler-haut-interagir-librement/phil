<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate;

use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplate\Exception\PlanRefused;
use Phil\CommerceProductTemplate\Internal\Check;

/**
 * Plans the products of a design made on a template: one per language version of the design and per combination the
 * template makes, the design allows and some channel sells, each with its SKU, its price and its artworks.
 *
 * The plan is the same for the same template and design: a generator runs it again to update a catalogue, and what
 * the plan no longer has is what it withdraws. It holds no state between two calls.
 */
final readonly class VariantPlanner
{
    /** The most products one plan gives. */
    public const int MAX_VARIANTS = 20_000;

    /**
     * @param int $maxVariants the most products one plan gives, at most `MAX_VARIANTS`
     */
    public function __construct(
        private int $maxVariants = self::MAX_VARIANTS,
    ) {
        if ($maxVariants < 1 || $maxVariants > self::MAX_VARIANTS) {
            throw InvalidDefinition::because(\sprintf('A plan holds from 1 to %d products, not %d.', self::MAX_VARIANTS, $maxVariants));
        }
    }

    /**
     * @return list<Variant> by language version, then in the order of the template's combinations
     *
     * @throws PlanRefused when the design is not made on the template, a SKU is invalid or shared, or an artwork names a
     *                     zone the template does not have or ties with another
     */
    public function plan(Template $template, Design $design): array
    {
        if (!$design->isMadeOn($template->code)) {
            throw PlanRefused::templateNotAllowed($design->code, $template->code);
        }

        $languages = $design->languages();
        $variants = [];
        $skus = [];

        foreach ([] === $languages ? [null] : $languages as $language) {
            foreach ($template->combinations() as $combination) {
                $channels = $this->channels($combination);

                if (!$design->allows($combination) || false === $channels) {
                    continue;
                }

                $sku = $template->skuPattern->render(['design' => $design->code, 'lang' => $language->code ?? '', 'template' => $template->code] + $combination->codes());
                $described = trim(($language->code ?? '').' '.$combination->describe());

                if (1 !== preg_match(Check::SKU_PATTERN, $sku)) {
                    throw PlanRefused::invalidSku($sku);
                }

                if (isset($skus[$sku])) {
                    throw PlanRefused::collision($sku, $skus[$sku], $described);
                }

                if (\count($variants) === $this->maxVariants) {
                    throw PlanRefused::tooMany($this->maxVariants);
                }

                $skus[$sku] = $described;
                $variants[] = new Variant($sku, $design, $template, $language, $combination, $template->pricing->priceOf($combination), $channels, $this->artworks($template, $design, $combination, $language?->code));
            }
        }

        return $variants;
    }

    /**
     * The channels every value allows: those all the values that name channels share; `false` when they share none.
     *
     * @return list<string>|false
     */
    private function channels(Combination $combination): array|false
    {
        $channels = null;

        foreach ($combination->values as $value) {
            if ([] !== $value->channels) {
                $channels = null === $channels ? $value->channels : array_values(array_intersect($channels, $value->channels));
            }
        }

        return null === $channels ? [] : ([] === $channels ? false : $channels);
    }

    /**
     * @return list<PlacedArtwork>
     */
    private function artworks(Template $template, Design $design, Combination $combination, ?string $language): array
    {
        /** @var array<string, array{Artwork, PlacedArtwork, bool}> $chosen the best artwork of each zone, and whether another ties with it */
        $chosen = [];

        foreach ($design->artworks as $artwork) {
            $face = $template->face($artwork->face);

            if (!$face instanceof Face || !$artwork->appliesTo($combination, $language)) {
                continue;
            }

            $zone = $face->zone($artwork->zone) ?? throw PlanRefused::unknownZone($design->code, $face->code, (string) $artwork->zone, $template->code);
            $slot = $face->code.'|'.$zone->name;
            $best = $chosen[$slot][0] ?? null;

            if (!$best instanceof Artwork || $artwork->specificity() > $best->specificity()) {
                $chosen[$slot] = [$artwork, new PlacedArtwork($face, $zone, $artwork->path), false];
            } elseif ($artwork->specificity() === $best->specificity()) {
                $chosen[$slot][2] = true;
            }
        }

        $placed = [];

        foreach ($template->faces() as $face) {
            foreach ($face->zones() as $zone) {
                $choice = $chosen[$face->code.'|'.$zone->name] ?? null;

                if (null === $choice) {
                    continue;
                }

                if ($choice[2]) {
                    throw PlanRefused::ambiguousArtwork($design->code, $face->code, $zone->name, $combination->describe());
                }

                $placed[] = $choice[1];
            }
        }

        return $placed;
    }
}
