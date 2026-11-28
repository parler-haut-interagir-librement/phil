<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Twig;

use Override;
use Phil\EmailTemplatesBundle\Application\Contract\StyleInliner;
use Twig\Environment;
use Twig\Extra\CssInliner\CssInlinerExtension;
use Twig\Loader\ArrayLoader;

/**
 * Inlines the styles of a document with the `inline_css` filter of `twig/cssinliner-extra`: each rule of the `<style>`
 * elements is copied onto the elements it matches, a declaration already inline winning; the media queries — the dark
 * mode — stay in their `<style>` element, which is kept.
 */
final class CssInlinerStyles implements StyleInliner
{
    private ?Environment $environment = null;

    #[Override]
    public function inline(string $html): string
    {
        $this->environment ??= $this->environment();

        return $this->environment->render('inline', ['html' => $html]);
    }

    private function environment(): Environment
    {
        $environment = new Environment(new ArrayLoader(['inline' => '{{ html|raw|inline_css }}']), ['autoescape' => false, 'cache' => false]);
        $environment->addExtension(new CssInlinerExtension());

        return $environment;
    }
}
