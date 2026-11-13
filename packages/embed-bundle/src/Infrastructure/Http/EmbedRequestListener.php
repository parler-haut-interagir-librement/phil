<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Infrastructure\Http;

use Phil\EmbedBundle\Application\Attribute\Embeddable;
use Phil\EmbedBundle\Application\Contract\FramingPolicy;
use Phil\EmbedBundle\Application\EmbedCodes;
use Phil\EmbedBundle\Application\EmbedTokens;
use Phil\EmbedBundle\Domain\Exception\InvalidEmbed;
use Phil\EmbedBundle\Domain\Exception\InvalidEmbedToken;
use Phil\EmbedBundle\Domain\ValueObject\Origin;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

/**
 * Answers the page of an embed for the token it carries: once the controller is known, a request to a route with an
 * `_embed` default, or to a controller marked `#[Embeddable]`, that carries `embed_token` is given its context — the
 * request attribute `_phil_embed` — and may be framed by the token's origin; with a token that is not valid, it may be
 * framed by none. Without a token, the page is answered as any other, under the site's own framing rules.
 */
final readonly class EmbedRequestListener
{
    public const string ATTRIBUTE = '_phil_embed';

    public const string ROUTE_DEFAULT = '_embed';

    public function __construct(
        private EmbedTokens $tokens,
        private FramingPolicy $framing,
        private ?LoggerInterface $logger = null,
        private bool $allowLocalhost = false,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $embed = $request->attributes->get(self::ROUTE_DEFAULT);
        $embed = \is_string($embed) ? $embed : ($event->getAttributes(Embeddable::class)[0] ?? null)?->embed;

        $query = $request->query->all();
        $token = $query[EmbedCodes::TOKEN] ?? null;

        if (null === $embed || !\is_string($token) || '' === $token) {
            return;
        }

        $theme = $query[EmbedCodes::THEME] ?? null;

        try {
            $context = $this->tokens->context($embed, $token, \is_string($theme) ? $theme : null);
            $origin = Origin::fromString($context->origin, $this->allowLocalhost);
        } catch (InvalidEmbedToken|InvalidEmbed $error) {
            $this->logger?->info('An embed token was refused: {reason}', ['reason' => $error->getMessage(), 'embed' => $embed]);
            $this->framing->deny($request);

            return;
        }

        $request->attributes->set(self::ATTRIBUTE, $context);
        $this->framing->allow($request, $origin);
    }
}
