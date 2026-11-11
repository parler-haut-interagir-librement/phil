<?php

declare(strict_types=1);

namespace Phil\SlugBundle\Infrastructure\Twig;

use Phil\Slug\InvalidSlug;
use Phil\Slug\Sluggers;
use Twig\Attribute\AsTwigFilter;

/**
 * Slugs in templates: `{{ name|phil_slug }}`, or `{{ name|phil_slug('names') }}` with a named slugger.
 */
final readonly class SlugExtension
{
    public function __construct(
        private Sluggers $sluggers,
    ) {
    }

    /**
     * The slug of a text, or an empty string for a text that holds nothing to make a slug of — a class name or a
     * sort key is better empty than an error page. An unknown slugger name is an error.
     */
    #[AsTwigFilter(name: 'phil_slug')]
    public function slug(?string $text, ?string $slugger = null): string
    {
        $made = $this->sluggers->get($slugger);

        try {
            return null === $text ? '' : $made->slug($text)->value;
        } catch (InvalidSlug) {
            return '';
        }
    }
}
