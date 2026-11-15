<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Fixture;

use Twig\Attribute\AsTwigFilter;

/**
 * The two filters of Tailwind's class merging the recipes of the Shadcn kit call, giving the classes back as they come:
 * the suites read the markup, not the style.
 */
final readonly class TailwindFilters
{
    #[AsTwigFilter(name: 'tailwind_merge')]
    public function merge(string $classes): string
    {
        return $classes;
    }

    #[AsTwigFilter(name: 'tailwind_classes')]
    public function classes(string $classes): string
    {
        return $classes;
    }
}
