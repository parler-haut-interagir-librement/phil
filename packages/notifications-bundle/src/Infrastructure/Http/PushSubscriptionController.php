<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Http;

use Closure;
use Phil\NotificationsBundle\Application\Contract\CurrentRecipient;
use Phil\NotificationsBundle\Application\Exception\InvalidPushSubscription;
use Phil\NotificationsBundle\Application\WebPush\PushSubscriber;
use Phil\NotificationsBundle\Application\WebPush\PushSubscription;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The endpoints of Web Push, for the recipient of the current request only, checked as the centre's changes are:
 * keeping the subscription the browser sent (`201`), and forgetting the endpoint it names (`204`, or `404` when the
 * recipient did not subscribe it). A visitor or a request from another site gets a `403`, a body that is no
 * subscription a `422`, a body longer than a subscription a `413`.
 */
final readonly class PushSubscriptionController
{
    public function __construct(
        private PushSubscriber $subscriber,
        private CurrentRecipient $current,
        private CentreRequest $requests,
    ) {
    }

    public function subscribe(Request $request): Response
    {
        return $this->handle($request, function (string $recipient, string $body): Response {
            $this->subscriber->subscribe($recipient, $body);

            return new JsonResponse(['subscribed' => true], Response::HTTP_CREATED);
        });
    }

    public function unsubscribe(Request $request): Response
    {
        return $this->handle($request, fn (string $recipient, string $body): Response => $this->subscriber->unsubscribe($recipient, $body)
                ? new Response(null, Response::HTTP_NO_CONTENT)
                : new JsonResponse(['error' => 'not_found'], Response::HTTP_NOT_FOUND));
    }

    /**
     * @param Closure(string, string): Response $action
     */
    private function handle(Request $request, Closure $action): Response
    {
        $recipient = $this->current->id();

        if (null === $recipient || !$this->requests->isTrusted($request)) {
            return new JsonResponse(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        if ((int) $request->headers->get('Content-Length', '0') > PushSubscription::MAX_JSON) {
            return new JsonResponse(['error' => 'too_large'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        try {
            return $action($recipient, $request->getContent());
        } catch (InvalidPushSubscription $invalidPushSubscription) {
            return new JsonResponse(['error' => 'invalid_subscription', 'message' => $invalidPushSubscription->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
