<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Functional\App;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * The page a table is drawn on: the two tables of the functional suite, or the template of the route's `template`.
 */
final readonly class PageController
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        return new Response($this->twig->render($request->attributes->getString('template', 'page.html.twig')));
    }
}
