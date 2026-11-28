<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Functional\App;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\RateLimit;
use Symfony\Component\RateLimiter\RateLimit as ConsumedRateLimit;

/**
 * Actions behind limiters: by Symfony's attribute on the class and the action, by route default, by a limiter of the
 * framework's configuration, and a form limited by name.
 */
#[RateLimit('burst')]
final readonly class TestController
{
    public function __construct(
        private FormFactoryInterface $forms,
    ) {
    }

    #[RateLimit('search', key: 'ip_route')]
    public function search(): Response
    {
        return new Response('found');
    }

    #[RateLimit('articles', key: '{id}', tokens: 2)]
    public function article(string $id, ?ConsumedRateLimit $limit): Response
    {
        return new Response($id.':'.($limit?->getRemainingTokens() ?? 'none'));
    }

    #[RateLimit('shared')]
    public function first(): Response
    {
        return new Response('first');
    }

    #[RateLimit('shared')]
    public function second(): Response
    {
        return new Response('second');
    }

    #[RateLimit('framework_limit')]
    public function framework(): Response
    {
        return new Response('framework');
    }

    public function api(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }

    public function contact(Request $request): Response
    {
        $form = $this->forms->createNamed('contact', FormType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return new Response('form');
        }

        return new Response($form->isValid() ? 'sent' : $form->getErrors()[0]->getMessage(), $form->isValid() ? 200 : 422);
    }
}
