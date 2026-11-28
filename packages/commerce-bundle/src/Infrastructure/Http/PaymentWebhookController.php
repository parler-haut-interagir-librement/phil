<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Infrastructure\Http;

use Doctrine\ORM\EntityManagerInterface;
use Phil\CommerceBundle\Application\NotificationOutcome;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Exception\InvalidNotification;
use Phil\CommerceBundle\Domain\Exception\PaymentRefused;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Receives a processor's notifications, at the route the application gives it with a `method` attribute.
 *
 * | Answer | When | What the processor does |
 * |---|---|---|
 * | 204 | Applied, already applied, refused by the payment's state, or ignored | Stops sending it |
 * | 400 | The gateway does not verify or read it | Retries, then gives up |
 * | 404 | No gateway for the method, or no payment by the processor's reference yet | Retries |
 * | 413 | Longer than `MAX_BYTES`, read to one byte past it and no further | Gives up |
 *
 * A notification applied is committed before the answer, so a 204 is never sent for a change that was lost. An
 * ignored notification — a verified event the store does not act on — changes nothing and is logged at the info
 * level with its id and the processor's type of the event, nothing else.
 */
final readonly class PaymentWebhookController
{
    /** The longest notification read: a processor's event is a few kilobytes. */
    public const int MAX_BYTES = 1_048_576;

    public function __construct(
        private Payments $payments,
        private EntityManagerInterface $entityManager,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(Request $request, string $method): Response
    {
        // Anyone may post here: the body is read to one byte past the limit and no further, before any verification.
        $payload = (string) stream_get_contents($request->getContent(true), self::MAX_BYTES + 1);

        if (\strlen($payload) > self::MAX_BYTES) {
            return new Response(null, Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $headers = [];

        foreach ($request->headers->keys() as $name) {
            $headers[$name] = array_values(array_filter($request->headers->all($name), \is_string(...)));
        }

        try {
            [$outcome, , $notification] = $this->payments->notify($method, $payload, $headers);
        } catch (InvalidNotification) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        } catch (PaymentRefused) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        if (NotificationOutcome::Ignored === $outcome) {
            $this->logger?->info('Payment notification {id} of type {type} ignored: the store does not act on it.', ['id' => $notification->id, 'type' => $notification->ignoredType, 'method' => $method]);

            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        if (NotificationOutcome::UnknownPayment === $outcome) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
