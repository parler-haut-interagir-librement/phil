<?php

declare(strict_types=1);

namespace Phil\FontsBundle\Infrastructure\Twig;

use Phil\FontsBundle\Application\FontFaces;
use Twig\Attribute\AsTwigFunction;

/**
 * `{{ fonts_css() }}`, inside a `<style>` element: the `@font-face` rules of the font directory. Every string taken
 * from a font is escaped by phil/fonts, so that nothing ends the rule or the element.
 */
final readonly class FontsExtension
{
    public function __construct(
        private FontFaces $faces,
    ) {
    }

    #[AsTwigFunction(name: 'fonts_css', isSafe: ['html'])]
    public function css(): string
    {
        return $this->faces->css();
    }
}
