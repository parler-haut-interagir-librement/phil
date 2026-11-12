<?php

declare(strict_types=1);

namespace Phil\TranslationMemory;

use Phil\TranslationMemory\Exception\InvalidLanguage;

/**
 * The regional variants translated as departures from their parent language: a `fr-CA` segment without a usable
 * target of its own takes the `fr` one, and only the segments that differ in Canada are translated again.
 *
 * Only the variants named here inherit; any other tag stands alone. The default names `fr-CA` and `en-CA`. A variant
 * whose parent is itself a variant inherits along the chain — `zh-Hant-TW`, `zh-Hant`, `zh` —, the nearest language
 * winning.
 */
final readonly class Variants
{
    /** The variants that inherit unless told otherwise. */
    public const array DEFAULT = ['fr-CA', 'en-CA'];

    /**
     * @var array<string, LanguageTag> the parent of each variant, by its canonical tag
     */
    private array $parents;

    /**
     * @param iterable<string|LanguageTag> $variants
     *
     * @throws InvalidLanguage when a variant is not a language tag, or a primary language without a parent
     */
    public function __construct(iterable $variants = self::DEFAULT)
    {
        $parents = [];

        foreach ($variants as $variant) {
            $tag = $variant instanceof LanguageTag ? $variant : LanguageTag::of($variant);
            $parents[$tag->value] = $tag->parent() ?? throw InvalidLanguage::noParent($tag->value);
        }

        $this->parents = $parents;
    }

    /**
     * No variant: every language stands alone.
     */
    public static function none(): self
    {
        return new self([]);
    }

    /**
     * The variants that inherit, as canonical tags, in the order given.
     *
     * @return list<string>
     */
    public function variants(): array
    {
        return array_keys($this->parents);
    }

    /**
     * The language a variant inherits from; `null` for a language that is not one of the variants.
     */
    public function parentOf(LanguageTag $language): ?LanguageTag
    {
        return $this->parents[$language->value] ?? null;
    }

    /**
     * The language and those it inherits from, nearest first: `fr-CA`, `fr`.
     *
     * @return non-empty-list<LanguageTag>
     */
    public function chain(LanguageTag $language): array
    {
        $chain = [$language];

        while (($parent = $this->parentOf(array_last($chain))) instanceof LanguageTag) {
            $chain[] = $parent;
        }

        return $chain;
    }

    /**
     * The segments of a variant with the usable targets of its parent where it has none of its own: a segment of the
     * variant that is neither translated nor reviewed — or missing — takes the parent's segment of the same key, when
     * that one is usable and made from the same source. The variant's own segments come first, in their order, then
     * the parent's segments it lacks.
     *
     * @param iterable<Segment> $own    the segments of the variant
     * @param iterable<Segment> $parent the segments of its parent, already resolved along the chain
     *
     * @return list<Segment>
     */
    public function inherit(iterable $own, iterable $parent): array
    {
        $inherited = [];

        foreach ($parent as $segment) {
            if ($segment->isUsable()) {
                $inherited[$segment->key] = $segment;
            }
        }

        $resolved = [];

        foreach ($own as $segment) {
            $fallback = $inherited[$segment->key] ?? null;
            unset($inherited[$segment->key]);
            $resolved[] = !$segment->isUsable() && $fallback instanceof Segment && $fallback->fingerprint->equals($segment->fingerprint) ? $fallback : $segment;
        }

        return [...$resolved, ...array_values($inherited)];
    }
}
