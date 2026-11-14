<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Remote;

use Phil\NotificationsBundle\Application\Contract\RemoteNotificationReceiver;
use Phil\NotificationsBundle\Application\Exception\UnknownNotificationType;
use Phil\NotificationsBundle\Application\RemoteNotification;
use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use Phil\StandardWebhooks\Exception\InvalidWebhookValue;
use Phil\StandardWebhooks\ProcessedWebhookRepository;
use Phil\StandardWebhooks\VerifiedWebhook;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Receives a notification from another site of the network: a Standard Webhooks delivery of type
 * `notification.created`, its signature verified by phil/standard-webhooks-bundle before this controller runs. The
 * origin it names must be one the configuration trusts; a delivery already processed is acknowledged without being
 * delivered again. A delivery this site cannot take — another type, a payload of the wrong shape, an origin it does not
 * trust, a notification type it does not declare — is a `422`, which the sender does not retry; a body that is no
 * envelope, a `400`.
 */
final readonly class RemoteNotificationController
{
    public const string EVENT = 'notification.created';

    /**
     * @param list<string> $origins the origins this site takes notifications from
     */
    public function __construct(
        private RemoteNotificationReceiver $receiver,
        private ClockInterface $clock,
        private array $origins = [],
        private ?ProcessedWebhookRepository $processed = null,
    ) {
    }

    public function __invoke(VerifiedWebhook $webhook): Response
    {
        try {
            $envelope = $webhook->envelope();
        } catch (InvalidWebhookValue $invalidWebhookValue) {
            return new JsonResponse(['error' => $invalidWebhookValue->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        if (self::EVENT !== $envelope->type) {
            return $this->refuse(\sprintf('This endpoint takes "%s" deliveries only.', self::EVENT));
        }

        try {
            $notification = RemoteNotification::fromPayload($envelope->data);
        } catch (InvalidNotification $invalidNotification) {
            return $this->refuse($invalidNotification->getMessage());
        }

        if (!\in_array($notification->origin, $this->origins, true)) {
            return $this->refuse(\sprintf('The origin "%s" is not trusted.', $notification->origin));
        }

        if ($this->processed instanceof ProcessedWebhookRepository && !$this->processed->claim($webhook->id, $this->clock->now())) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        try {
            $this->receiver->receive($notification);
        } catch (UnknownNotificationType $unknownNotificationType) {
            return $this->refuse($unknownNotificationType->getMessage());
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function refuse(string $reason): JsonResponse
    {
        return new JsonResponse(['error' => $reason], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
