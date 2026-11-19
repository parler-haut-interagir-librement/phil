<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Infrastructure\Http;

use Doctrine\ORM\EntityManagerInterface;
use Phil\CommerceBundle\Domain\Exception\InvalidNotification;
use Phil\CommercePosBundle\Application\TerminalPayments;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where the processors of the connected terminals send their notifications: the application routes `POST` requests
 * to it, outside any login, since the adapter verifies each one's signature. It answers `400` for a notification no
 * adapter recognises or verifies, and `200` for any other — applied, already applied, refused by the payment's state,
 * or for a payment it does not know —, so that the processor stops sending it.
 */
final readonly class TerminalNotificationController
{
    /** The largest body read: a processor's event is a few kilobytes. */
    public const int MAX_BODY = 65_536;

    public function __construct(
        private TerminalPayments $payments,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();

        if (\strlen($payload) > self::MAX_BODY) {
            return new Response('', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        // Symfony's header bag names every header in lowercase, as the adapters read them.
        $headers = [];

        foreach ($request->headers->keys() as $name) {
            $headers[$name] = array_values(array_filter($request->headers->all($name), \is_string(...)));
        }

        try {
            $this->payments->notify($payload, $headers, $this->clock->now());
        } catch (InvalidNotification) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return new Response('', Response::HTTP_OK);
    }
}
