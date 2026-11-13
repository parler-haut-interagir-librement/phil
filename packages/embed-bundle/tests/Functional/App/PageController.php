<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Tests\Functional\App;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Renders the template its route names.
 */
final readonly class PageController
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function __invoke(string $template): Response
    {
        return new Response($this->twig->render($template));
    }
}
