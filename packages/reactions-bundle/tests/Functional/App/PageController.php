<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Functional\App;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * A page showing a content twice, and another once.
 */
final readonly class PageController
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function __invoke(): Response
    {
        return new Response($this->twig->render('page.html.twig'));
    }
}
