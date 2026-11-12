<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Infrastructure\Http;

use Phil\SocialCards\Preview\LinkPreview;
use Phil\SocialCardsBundle\Application\LinkPreviews;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * `/social-cards/link-preview?url=…`: the preview of a link a member pasted in a form, as JSON, for the
 * `link-preview` Stimulus controller — `200` and the preview, `204` when the page gives none, `400` when the address
 * is not one the bundle fetches, `429` when the visitor's rate limiter is spent. The answer is `private`, kept five
 * minutes by the browser; the preview itself is kept in the cache of `LinkPreviews`.
 *
 * Each call may fetch a page of another site: the route is imported apart, behind the firewall of the members who
 * write, and a rate limiter, keyed by the client's address, bounds what one visitor fetches.
 */
final readonly class LinkPreviewController
{
    public const string ROUTE = 'phil_social_cards_link_preview';

    public function __construct(
        private LinkPreviews $previews,
        private ?RateLimiterFactoryInterface $limiter = null,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $url = $request->query->get('url');

        if (!\is_string($url) || !LinkPreviews::isFetchable($url)) {
            return $this->private(new JsonResponse(['error' => 'The address is not an http or https address of a host name.'], Response::HTTP_BAD_REQUEST));
        }

        $limit = $this->limiter?->create($request->getClientIp() ?? 'unknown')->consume();

        if ($limit instanceof RateLimit && !$limit->isAccepted()) {
            $response = new JsonResponse(['error' => 'Too many previews; try again later.'], Response::HTTP_TOO_MANY_REQUESTS);
            $response->headers->set('Retry-After', (string) max(1, $limit->getRetryAfter()->getTimestamp() - time()));

            return $this->private($response);
        }

        $preview = $this->previews->preview($url);

        return $this->private($preview instanceof LinkPreview ? new JsonResponse($preview->toArray()) : new Response('', Response::HTTP_NO_CONTENT));
    }

    private function private(Response $response): Response
    {
        $response->setPrivate();
        $response->setMaxAge(300);
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
