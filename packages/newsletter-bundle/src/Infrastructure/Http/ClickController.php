<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Http;

use Phil\NewsletterBundle\Application\Exception\LinkRefused;
use Phil\NewsletterBundle\Application\Exception\NotFound;
use Phil\NewsletterBundle\Application\Tracking\ClickTracker;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects a tracked link to the address its campaign holds, counting the click unless a machine asked; a link that
 * is not the newsletter's gets a `404`.
 */
final readonly class ClickController
{
    public function __construct(
        private ClickTracker $tracker,
    ) {
    }

    public function __invoke(Request $request, string $token): Response
    {
        $headers = [];

        foreach (['sec-purpose', 'purpose', 'x-purpose', 'x-moz'] as $name) {
            $headers[$name] = $request->headers->get($name);
        }

        try {
            $url = $this->tracker->follow($token, $request->getMethod(), $headers);
        } catch (LinkRefused|NotFound) {
            return new Response('', Response::HTTP_NOT_FOUND, ['Cache-Control' => 'no-store']);
        }

        return new RedirectResponse($url, Response::HTTP_FOUND, ['Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer']);
    }
}
