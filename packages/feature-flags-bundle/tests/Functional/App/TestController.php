<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Functional\App;

use Phil\FeatureFlagsBundle\Infrastructure\Http\IsFeatureEnabled;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * The application's own controllers, placed behind flags at the edge.
 */
final readonly class TestController
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    #[IsFeatureEnabled('new-checkout')]
    public function checkout(): Response
    {
        return new Response('new checkout');
    }

    #[IsFeatureEnabled('checkout-layout', variant: 'two-columns')]
    public function twoColumns(): Response
    {
        return new Response('two columns');
    }

    public function page(): Response
    {
        return new Response($this->twig->render('page.html.twig'));
    }

    public function current(): Response
    {
        return new Response('current feature');
    }

    public function beta(): Response
    {
        return new Response('beta feature');
    }
}
