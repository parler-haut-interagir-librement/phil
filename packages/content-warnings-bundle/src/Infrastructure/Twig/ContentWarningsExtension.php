<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle\Infrastructure\Twig;

use Phil\ContentWarnings\ContentWarnings;
use Phil\ContentWarnings\Decision;
use Phil\ContentWarningsBundle\Application\CatalogueLabels;
use Phil\ContentWarningsBundle\Application\WarningScreen;
use Twig\Attribute\AsTwigFunction;

/**
 * Warnings in templates: the decision for the current member, which `@PhilContentWarnings/fold.html.twig` renders,
 * and the labels of themes and categories in the current locale.
 */
final readonly class ContentWarningsExtension
{
    public function __construct(
        private WarningScreen $screen,
        private CatalogueLabels $labels,
    ) {
    }

    #[AsTwigFunction(name: 'content_warnings_decision')]
    public function decision(?ContentWarnings $warnings): Decision
    {
        return $this->screen->decide($warnings);
    }

    #[AsTwigFunction(name: 'content_warning_label')]
    public function label(string $theme, ?string $locale = null): string
    {
        return $this->labels->theme($theme, $locale);
    }

    #[AsTwigFunction(name: 'content_warning_description')]
    public function description(string $theme, ?string $locale = null): string
    {
        return $this->labels->description($theme, $locale);
    }

    /**
     * The label of the theme's category; empty for a theme the catalogue does not have.
     */
    #[AsTwigFunction(name: 'content_warning_category')]
    public function category(string $theme, ?string $locale = null): string
    {
        $category = $this->labels->catalogue()->theme($theme)?->category;

        return null === $category ? '' : $this->labels->category($category, $locale);
    }
}
