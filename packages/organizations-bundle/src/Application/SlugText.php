<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application;

use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\Slug\InvalidSlug;
use Phil\Slug\Slug;
use Phil\Slug\Slugger;

/**
 * The slugs of organizations and teams: one given is checked and never fixed; one made from a name by the slugger.
 */
final readonly class SlugText
{
    private function __construct()
    {
    }

    /**
     * @throws InvalidOrganization when the text is not a slug
     */
    public static function given(string $slug): Slug
    {
        try {
            return Slug::fromString($slug);
        } catch (InvalidSlug $invalidSlug) {
            throw InvalidOrganization::because($invalidSlug->getMessage());
        }
    }

    /**
     * @throws InvalidOrganization when the name holds nothing a slug can be made of
     */
    public static function made(Slugger $slugger, string $name): Slug
    {
        try {
            return $slugger->slug($name);
        } catch (InvalidSlug $invalidSlug) {
            throw InvalidOrganization::because($invalidSlug->getMessage().' Give the slug.');
        }
    }

    /**
     * The slug with a numbered suffix, cut so that it stays a slug.
     */
    public static function numbered(Slug $slug, int $number): Slug
    {
        return Slug::fromString(rtrim(substr($slug->value, 0, Slug::MAX_LENGTH - 4), '-').'-'.$number);
    }
}
