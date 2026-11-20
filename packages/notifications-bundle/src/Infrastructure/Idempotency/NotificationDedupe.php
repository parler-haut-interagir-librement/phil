<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Idempotency;

use Closure;
use Override;
use Phil\Idempotency\Exception\ReservationLost;
use Phil\Idempotency\IdempotencyGuard;
use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyReservation;
use Phil\Idempotency\IdempotencyScope;
use Phil\Idempotency\PayloadFingerprint;
use Phil\Idempotency\StoredResponse;
use Phil\NotificationsBundle\Application\Contract\DeliveryDedupe;
use Phil\NotificationsBundle\Application\Delivery\DedupeTicket;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use Phil\NotificationsBundle\Application\NotificationType;
use Throwable;

/**
 * One delivery of a notification to a recipient per window, on `phil/idempotency`.
 *
 * The key is the message's own `dedupeKey`, or else what the message says — its type, subject, data, actor and origin
 * —, with the recipient: never the identifier a delivery gives the notification, which is new at each retry. `begin()`
 * reserves it atomically, so that two workers handling the same message never both send; the delivery then completes
 * it, and the key is kept for the window, or fails it, which lets the next attempt run. The centre is kept once per key
 * under a second scope, so that a retry after a total failure does not add the notification to it twice.
 *
 * The store must be shared by every server: a local cache pool would let each server send once.
 */
final readonly class NotificationDedupe implements DeliveryDedupe
{
    public const string SCOPE = 'phil_notifications.delivery';

    public const string CENTRE_SCOPE = 'phil_notifications.centre';

    /** The window, in seconds, of a type that names none. */
    public const int TTL = 300;

    public function __construct(
        private IdempotencyGuard $guard,
        private int $ttl = self::TTL,
    ) {
    }

    #[Override]
    public function begin(SendNotification $message, NotificationType $type, string $recipient): ?DedupeTicket
    {
        $ttl = $this->ttl($type);

        if (0 === $ttl) {
            return null;
        }

        $reservation = $this->guard->begin(IdempotencyScope::of(self::SCOPE), $this->key($message, $type, $recipient), $this->fingerprint($message, $type, $recipient), $ttl)->reservation;

        return $reservation instanceof IdempotencyReservation ? DedupeTicket::reserved($reservation) : DedupeTicket::duplicate();
    }

    #[Override]
    public function complete(DedupeTicket $ticket, NotificationType $type): void
    {
        $reservation = $ticket->reservation;

        if (!$reservation instanceof IdempotencyReservation) {
            return;
        }

        try {
            $this->guard->complete($reservation, StoredResponse::nothing(), $this->ttl($type));
        } catch (ReservationLost) {
            // The delivery outlived its reservation and another run took the key: that run answers for it.
        }
    }

    #[Override]
    public function release(DedupeTicket $ticket, NotificationType $type): void
    {
        $reservation = $ticket->reservation;

        if (!$reservation instanceof IdempotencyReservation) {
            return;
        }

        try {
            $this->guard->fail($reservation, $this->ttl($type));
        } catch (ReservationLost) {
            // Another run holds the key since the reservation expired: it is that run's to complete or fail.
        }
    }

    #[Override]
    public function once(SendNotification $message, NotificationType $type, string $recipient, Closure $record): bool
    {
        $ttl = $this->ttl($type);

        if (0 === $ttl) {
            $record();

            return true;
        }

        $reservation = $this->guard->begin(IdempotencyScope::of(self::CENTRE_SCOPE), $this->key($message, $type, $recipient), $this->fingerprint($message, $type, $recipient), $ttl)->reservation;

        if (!$reservation instanceof IdempotencyReservation) {
            return false;
        }

        $ticket = DedupeTicket::reserved($reservation);

        try {
            $record();
        } catch (Throwable $throwable) {
            $this->release($ticket, $type);

            throw $throwable;
        }

        $this->complete($ticket, $type);

        return true;
    }

    private function ttl(NotificationType $type): int
    {
        return $type->dedupeTtl ?? $this->ttl;
    }

    private function key(SendNotification $message, NotificationType $type, string $recipient): IdempotencyKey
    {
        return IdempotencyKey::fromString(hash('sha256', implode("\0", [$type->key, $recipient, $this->source($message)])));
    }

    private function fingerprint(SendNotification $message, NotificationType $type, string $recipient): PayloadFingerprint
    {
        return PayloadFingerprint::ofParts($type->key, $recipient, $this->source($message));
    }

    /**
     * What makes two notifications the same: the message's key, or its subject, data, actor and origin.
     */
    private function source(SendNotification $message): string
    {
        if (null !== $message->dedupeKey) {
            return 'key:'.$message->dedupeKey;
        }

        return 'content:'.json_encode([$message->subjectType, $message->subjectId, $message->data, $message->actor, $message->origin], \JSON_THROW_ON_ERROR);
    }
}
