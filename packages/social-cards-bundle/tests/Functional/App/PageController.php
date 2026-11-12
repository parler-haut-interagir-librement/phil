<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Tests\Functional\App;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * A page of the application: its template sets its share values and writes its tags.
 */
final readonly class PageController
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function __invoke(): Response
    {
        return new Response($this->twig->render('page.html.twig', ['title' => 'Un « article »', 'description' => 'Son résumé']));
    }
}
