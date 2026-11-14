<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Functional\App;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class EmbedController
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function embed(): Response
    {
        return new Response($this->twig->render('embed.html.twig'));
    }

    /**
     * A page that writes a cookie the inventory does not declare.
     */
    public function tracker(): Response
    {
        $response = new Response('tracked');
        $response->headers->setCookie(Cookie::create('_tracker', 'x'));
        $response->headers->setCookie(Cookie::create('_visit', 'x'));

        return $response;
    }
}
