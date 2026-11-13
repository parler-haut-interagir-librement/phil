<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Tests\Functional\App;

use Phil\EmbedBundle\Application\Attribute\Embeddable;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * The page of the embed "form", marked by its attribute rather than by its route.
 */
#[Embeddable('form')]
final readonly class FormController
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function __invoke(): Response
    {
        return new Response($this->twig->render('frame.html.twig'));
    }
}
