<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate;

use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplate\Internal\Check;

/**
 * A design made on templates: its code, the first part of its SKUs (`NAQ001`), its name, its language versions, its
 * artworks, the templates it is made on and the values of their axes it allows.
 */
final readonly class Design
{
    /** The most language versions of a design. */
    public const int MAX_LANGUAGES = 8;

    /** The most artworks of a design. */
    public const int MAX_ARTWORKS = 200;

    /**
     * @param array<string, LanguageVersion> $languages by code, in order
     * @param list<Artwork>                  $artworks
     * @param list<string>                   $templates the codes of the templates it is made on; every one when empty
     * @param array<string, list<string>>    $allowed   the values it allows, by axis; every value of an axis not named
     */
    private function __construct(
        public string $code,
        public Label $name,
        private array $languages,
        public array $artworks,
        public array $templates,
        public array $allowed,
    ) {
    }

    /**
     * @param string                      $code      1 to 32 letters, digits, ".", "_" or "-", starting with a letter or
     *                                               digit; `{design}` in a SKU pattern
     * @param list<LanguageVersion>       $languages its language versions; none for a design in one language
     * @param list<Artwork>               $artworks
     * @param list<string>                $templates the codes of the templates it is made on; every one when empty
     * @param array<string, list<string>> $allowed   the values it allows, by axis — the colours it is printed on —;
     *                                               every value of an axis it does not name
     */
    public static function of(string $code, Label $name, array $languages = [], array $artworks = [], array $templates = [], array $allowed = []): self
    {
        Check::skuCode($code, 'a design', 32);

        if (\count($languages) > self::MAX_LANGUAGES) {
            throw InvalidDefinition::because(\sprintf('The design %s has at most %d language versions.', $code, self::MAX_LANGUAGES));
        }

        $keyed = [];

        foreach ($languages as $language) {
            if (isset($keyed[$language->code])) {
                throw InvalidDefinition::because(\sprintf('The design %s lists the language %s twice.', $code, $language->code));
            }

            $keyed[$language->code] = $language;
        }

        if (\count($artworks) > self::MAX_ARTWORKS) {
            throw InvalidDefinition::because(\sprintf('The design %s has at most %d artworks.', $code, self::MAX_ARTWORKS));
        }

        $slots = [];

        foreach ($artworks as $artwork) {
            $artwork->checkLanguage(array_keys($keyed));

            if (isset($slots[$artwork->slot()])) {
                throw InvalidDefinition::because(\sprintf('The design %s has two artworks for the same zone, language and combinations: %s and %s.', $code, $slots[$artwork->slot()], $artwork->path));
            }

            $slots[$artwork->slot()] = $artwork->path;
        }

        foreach ($templates as $template) {
            Check::skuCode($template, 'a template', 32);
        }

        return new self($code, $name, $keyed, $artworks, array_values(array_unique($templates)), Combination::condition($allowed, \sprintf('The values the design %s allows', $code)));
    }

    /**
     * @return list<LanguageVersion> in order
     */
    public function languages(): array
    {
        return array_values($this->languages);
    }

    public function language(string $code): ?LanguageVersion
    {
        return $this->languages[$code] ?? null;
    }

    public function isMadeOn(string $template): bool
    {
        return [] === $this->templates || \in_array($template, $this->templates, true);
    }

    /**
     * Whether the design allows each value of the combination; an axis it does not name allows every value.
     */
    public function allows(Combination $combination): bool
    {
        foreach ($this->allowed as $axis => $codes) {
            $value = $combination->value($axis);

            if ($value instanceof AxisValue && !\in_array($value->code, $codes, true)) {
                return false;
            }
        }

        return true;
    }
}
