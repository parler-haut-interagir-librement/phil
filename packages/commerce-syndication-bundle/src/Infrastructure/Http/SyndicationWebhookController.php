<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Infrastructure\Http;

use Doctrine\ORM\EntityManagerInterface;
use Phil\CommerceSyndicationBundle\Application\Inbox;
use Phil\CommerceSyndicationBundle\Domain\Exception\SyndicationRefused;
use Phil\CommerceSyndicationBundle\Domain\Exception\UnknownPartner;
use Phil\StandardWebhooks\Exception\WebhookException;
use Phil\StandardWebhooks\WebhookHeaders;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where partners post their messages, one URL per partner: the application routes `POST` requests to it with a
 * `partner` attribute. It answers `204` for a message applied or already received, `404` for an unknown partner,
 * `400` for a message refused — badly signed, stale, addressed elsewhere, or not one the partner may send —, `413`
 * for a body longer than `MAX_BYTES`, read to one byte past it and no further.
 */
final readonly class SyndicationWebhookController
{
    /** The longest message read, as the receivers of Standard Webhooks take by default. */
    public const int MAX_BYTES = 1_048_576;

    public function __construct(
        private Inbox $inbox,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request, string $partner): Response
    {
        // The signature is checked on the bytes received: they are read to one byte past the limit and no further.
        $body = (string) stream_get_contents($request->getContent(true), self::MAX_BYTES + 1);

        if (\strlen($body) > self::MAX_BYTES) {
            return new Response(null, Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        try {
            $headers = WebhookHeaders::fromStrings(
                $request->headers->get('webhook-id'),
                $request->headers->get('webhook-timestamp'),
                $request->headers->get('webhook-signature'),
            );
            $this->inbox->receive($partner, $headers, $body, $this->clock->now());
        } catch (UnknownPartner) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        } catch (SyndicationRefused|WebhookException) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
