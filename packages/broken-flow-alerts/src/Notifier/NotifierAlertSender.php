<?php

declare(strict_types=1);

namespace Phil\BrokenFlowAlerts\Notifier;

use Override;
use Phil\BrokenFlowAlerts\Contract\AlertSender;
use Phil\BrokenFlowAlerts\FlowAlert;
use Phil\BrokenFlowAlerts\FlowAlertKind;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * Sends each alert as a Symfony Notifier notification: high importance for a broken flow, low for a recovery, so that
 * the application's channel policy decides where each goes. Without recipients, the notification goes to the channels
 * that need none, such as a chat room.
 */
final readonly class NotifierAlertSender implements AlertSender
{
    /**
     * @var list<RecipientInterface>
     */
    private array $recipients;

    public function __construct(
        private NotifierInterface $notifier,
        RecipientInterface ...$recipients,
    ) {
        $this->recipients = array_values($recipients);
    }

    #[Override]
    public function send(FlowAlert $alert): void
    {
        $this->notifier->send(self::notificationOf($alert), ...$this->recipients);
    }

    public static function notificationOf(FlowAlert $alert): Notification
    {
        if (FlowAlertKind::Recovered === $alert->kind) {
            return new Notification(\sprintf('Flow "%s" completes again', $alert->flow->label))
                ->content(\sprintf('%d completions on %s.', $alert->observed, $alert->day))
                ->importance(Notification::IMPORTANCE_LOW);
        }

        return new Notification(\sprintf('Flow "%s" stopped completing', $alert->flow->label))
            ->content(\sprintf(
                '%d completions from %s to %s, where about %d were expected.',
                $alert->observed,
                $alert->since,
                $alert->day,
                (int) round($alert->expected),
            ))
            ->importance(Notification::IMPORTANCE_HIGH);
    }
}
