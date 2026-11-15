<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Functional\App;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * A page of the site with the report form of a post.
 */
final readonly class PostController
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function __invoke(): Response
    {
        return new Response($this->twig->render('post.html.twig'));
    }
}
