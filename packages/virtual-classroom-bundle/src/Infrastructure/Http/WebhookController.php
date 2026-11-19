<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Infrastructure\Http;

use Phil\VirtualClassroom\Exception\InvalidValue;
use Phil\VirtualClassroom\Exception\InvalidWebhook;
use Phil\VirtualClassroom\Exception\Unsupported;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroom\Webhook;
use Phil\VirtualClassroomBundle\Application\Classrooms;
use Phil\VirtualClassroomBundle\Application\WebhookSigner;
use Phil\VirtualClassroomBundle\Domain\Exception\UnknownProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Receives the notifications of the servers. The address carries its proof ({@see WebhookSigner}): a wrong one is a
 * 404, as an address that does not exist. A body over the ceiling is a 413; a notification the provider refuses —
 * its own signature, its age, its form — a 401, which BigBlueButton reads as a secret that does not match and stops
 * retrying; what the application knows is applied and answered with a 204, the events of rooms it does not know
 * ignored.
 */
final readonly class WebhookController
{
    public const string ROUTE = 'phil_virtual_classroom_webhook';

    public const int MAX_BYTES = 1_048_576;

    public function __construct(
        private Classrooms $classrooms,
        private WebhookSigner $signer,
    ) {
    }

    public function __invoke(Request $request, string $provider, string $token, ?string $room = null, string $channel = Webhook::ACCOUNT): Response
    {
        if (!$this->signer->verify($token, $provider, $room, $channel)) {
            throw new NotFoundHttpException('No webhook has this address.');
        }

        $length = $request->headers->get('Content-Length');

        if (null !== $length && (int) $length > self::MAX_BYTES) {
            return new Response('', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $body = $request->getContent();

        if (\strlen($body) > self::MAX_BYTES) {
            return new Response('', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            if (\is_string($name)) {
                $headers[$name] = $values[0] ?? '';
            }
        }

        $query = $request->query->all();

        try {
            $this->classrooms->receive($provider, null === $room ? null : RoomId::fromString($room), new Webhook($request->getMethod(), $headers, $query, $body, $channel));
        } catch (UnknownProvider|Unsupported|InvalidValue) {
            throw new NotFoundHttpException('No webhook has this address.');
        } catch (InvalidWebhook) {
            return new Response('', Response::HTTP_UNAUTHORIZED);
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
