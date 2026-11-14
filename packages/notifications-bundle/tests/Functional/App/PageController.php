<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Functional\App;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * A page of the application with the bell, the list and the toasts; the route names the template.
 */
final readonly class PageController
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function __invoke(string $template = 'page.html.twig'): Response
    {
        return new Response($this->twig->render($template));
    }
}
