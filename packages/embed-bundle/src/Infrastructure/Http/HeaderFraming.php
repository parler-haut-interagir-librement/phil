<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Infrastructure\Http;

use Override;
use Phil\EmbedBundle\Application\Contract\FramingPolicy;
use Phil\EmbedBundle\Domain\ValueObject\Origin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * The framing written by the bundle itself, without phil/browser-security-bundle: the decision is kept on the request,
 * and the response gets a `Content-Security-Policy` header of its own with `frame-ancestors` — beside any other policy
 * the application sends, which the browser enforces too —, and loses `X-Frame-Options` when it is allowed.
 */
final readonly class HeaderFraming implements FramingPolicy
{
    public const string ATTRIBUTE = '_phil_embed_frame_ancestors';

    #[Override]
    public function allow(Request $request, Origin $origin): void
    {
        $request->attributes->set(self::ATTRIBUTE, $origin->toString());
    }

    #[Override]
    public function deny(Request $request): void
    {
        $request->attributes->set(self::ATTRIBUTE, "'none'");
    }

    public function onResponse(ResponseEvent $event): void
    {
        $ancestors = $event->getRequest()->attributes->get(self::ATTRIBUTE);

        if (!$event->isMainRequest() || !\is_string($ancestors)) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('Content-Security-Policy', 'frame-ancestors '.$ancestors, false);

        if ("'none'" !== $ancestors) {
            $headers->remove('X-Frame-Options');
        }
    }
}
