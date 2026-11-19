<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Infrastructure\Notifications;

use Override;
use Phil\ErrorTracking\Contract\GroupAlerter;
use Phil\ErrorTracking\ErrorGroup;
use Phil\ErrorTracking\Occurrence;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * Sends the alert through phil/notifications-bundle, to recipients or to an audience. The notification carries the
 * fingerprint of the group, the occurrence and the count; what the error says is read from the store when the
 * notification is shown, by `ErrorGroupSubjects`.
 */
final readonly class NotificationAlerter implements GroupAlerter
{
    public const string TYPE = 'phil_error_tracking.group_alert';

    public const string SUBJECT = 'phil_error_tracking.group';

    /**
     * @param list<string> $recipients the identifiers notified, when no audience is named
     * @param ?string      $audience   the audience notified, instead of recipients
     */
    public function __construct(
        private Notifications $notifications,
        private array $recipients = [],
        private ?string $audience = null,
    ) {
    }

    #[Override]
    public function alert(ErrorGroup $group, Occurrence $occurrence): void
    {
        $subject = new SubjectReference(self::SUBJECT, $group->fingerprint);
        $data = ['occurrence' => $occurrence->value, 'seen' => $group->timesSeen];

        if (null === $this->audience) {
            $this->notifications->send(self::TYPE, $this->recipients, $subject, $data);

            return;
        }

        $this->notifications->sendToAudience(self::TYPE, $this->audience, $subject, $data);
    }
}
