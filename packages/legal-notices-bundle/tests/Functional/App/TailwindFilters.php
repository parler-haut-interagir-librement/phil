<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Tests\Functional\App;

use Twig\Attribute\AsTwigFilter;

/**
 * The filter of Tailwind's classes the recipes of the Shadcn kit call, giving the classes back as they come: the tests
 * read the markup, not the style.
 */
final readonly class TailwindFilters
{
    #[AsTwigFilter(name: 'tailwind_classes')]
    public function classes(string $classes): string
    {
        return $classes;
    }
}
