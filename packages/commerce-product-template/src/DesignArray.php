<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate;

use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplate\Internal\Reader;

/**
 * A design as an array of scalars, for a JSON column or a file, and back: `fromArray()` checks every value as the
 * constructors do, and refuses an array of another version.
 */
final class DesignArray
{
    public const int VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public static function toArray(Design $design): array
    {
        return [
            'version' => self::VERSION,
            'code' => $design->code,
            'name' => $design->name->texts(),
            'languages' => array_map(static fn (LanguageVersion $language): array => ['code' => $language->code, 'locale' => $language->locale], $design->languages()),
            'artworks' => array_map(static fn (Artwork $artwork): array => [
                'face' => $artwork->face,
                'path' => $artwork->path,
                'zone' => $artwork->zone,
                'language' => $artwork->language,
                'when' => $artwork->when,
            ], $design->artworks),
            'templates' => $design->templates,
            'allowed' => $design->allowed,
        ];
    }

    /**
     * @param array<array-key, mixed> $data as `toArray()` writes it
     *
     * @throws InvalidDefinition for an array of another version, a value of the wrong type, or a design refused
     */
    public static function fromArray(array $data): Design
    {
        $root = new Reader($data, 'design');
        $root->version(self::VERSION);

        return Design::of(
            $root->string('code'),
            Label::of($root->stringMap('name')),
            array_map(static fn (Reader $language): LanguageVersion => LanguageVersion::of($language->string('code'), $language->string('locale')), $root->readers('languages')),
            array_map(static fn (Reader $artwork): Artwork => Artwork::of(
                $artwork->string('face'),
                $artwork->string('path'),
                $artwork->nullableString('zone'),
                $artwork->nullableString('language'),
                Combination::condition($artwork->map('when'), 'The condition of an artwork'),
            ), $root->readers('artworks')),
            $root->strings('templates'),
            Combination::condition($root->map('allowed'), 'The values a design allows'),
        );
    }
}
