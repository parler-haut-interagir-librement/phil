<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate;

use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplate\Internal\Check;

/**
 * An image of a design, printed in a zone of a face: in every language of the design or in one, on every
 * combination or on those whose values the condition lists — the artwork of one version, of light colours.
 *
 * On each zone of each product, the most specific artwork that applies is printed: one of its language before one of
 * every language, then the one whose condition names the most axes.
 */
final readonly class Artwork
{
    /**
     * @param array<string, list<string>> $when value codes by axis code
     */
    private function __construct(
        public string $face,
        public string $path,
        public ?string $zone,
        public ?string $language,
        public array $when,
    ) {
    }

    /**
     * @param string                      $face     the code of the face it is printed on
     * @param string                      $path     the image, relative to the store of images; the colour print file
     *                                              of the zone, at its proportions
     * @param string|null                 $zone     the zone of the face; its first zone when `null`
     * @param string|null                 $language the code of the language version it belongs to; every one when `null`
     * @param array<string, list<string>> $when     the combinations it applies to, by axis; every one when empty
     */
    public static function of(string $face, string $path, ?string $zone = null, ?string $language = null, array $when = []): self
    {
        Check::identifier($face, 'a face');
        Check::path($path, 'an artwork');

        if (null !== $zone) {
            Check::identifier($zone, 'a print zone');
        }

        if (null !== $language) {
            Check::skuCode($language, 'a language version', 8);
        }

        return new self($face, $path, $zone, $language, Combination::condition($when, \sprintf('The condition of the artwork %s', $path)));
    }

    /**
     * Whether it applies to a product of this combination, in this language version.
     */
    public function appliesTo(Combination $combination, ?string $language): bool
    {
        return (null === $this->language || $this->language === $language) && $combination->matches($this->when);
    }

    /**
     * How specific it is: a language counts more than any condition.
     */
    public function specificity(): int
    {
        return (null === $this->language ? 0 : Template::MAX_AXES + 1) + \count($this->when);
    }

    /**
     * The face, zone, language and condition: two artworks of a design never share them.
     */
    public function slot(): string
    {
        $when = $this->when;
        ksort($when);

        return $this->face.'|'.($this->zone ?? '').'|'.($this->language ?? '').'|'.json_encode(array_map(static function (array $codes): array {
            sort($codes);

            return $codes;
        }, $when), \JSON_THROW_ON_ERROR);
    }

    /**
     * @internal the language versions a design checks its artworks against
     *
     * @param list<string> $languages
     */
    public function checkLanguage(array $languages): void
    {
        if (null !== $this->language && !\in_array($this->language, $languages, true)) {
            throw InvalidDefinition::because(\sprintf('The artwork %s belongs to the language %s, which its design is not made in.', $this->path, $this->language));
        }
    }
}
