<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Functional\App;

use Phil\RateLimitBundle\Domain\Attribute\RateLimit;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Actions behind limiters: by attribute on the class and the action, by route default, and a form limited by name.
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
