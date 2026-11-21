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
 *
 * A notification applied is committed before the answer, so a 204 is never sent for a change that was lost. An
 * ignored notification — a verified event the store does not act on — changes nothing and is logged at the info
 * level with its id and the processor's type of the event, nothing else.
 */
final readonly class PaymentWebhookController
{
    public function __construct(
        private Payments $payments,
        private EntityManagerInterface $entityManager,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(Request $request, string $method): Response
    {
        $headers = [];

        foreach ($request->headers->keys() as $name) {
            $headers[$name] = array_values(array_filter($request->headers->all($name), \is_string(...)));
        }

        try {
            [$outcome, , $notification] = $this->payments->notify($method, $request->getContent(), $headers);
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
