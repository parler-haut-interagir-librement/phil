<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Delivery;

use Phil\NotificationsBundle\Application\Channels;

/**
 * How a notification was delivered to one recipient: its status, the channels that took it, in order — the centre,
 * an email left to the digest, a channel whose provider accepted it —, and the reason each other channel of the type
 * was skipped. Identifiers and channel names only: what `context()` gives can go to the logs.
 */
final readonly class DeliveryResult
{
    /**
     * @param list<string>              $channels the channels that took the notification, in order
     * @param array<string, SkipReason> $skipped  the reason of each channel skipped, by channel
     */
    public function __construct(
        public string $type,
        public string $recipient,
        public DeliveryMode $mode,
        public DeliveryStatus $status,
        public array $channels = [],
        public array $skipped = [],
    ) {
    }

    /**
     * The first channel outside the centre that took the notification: the one `first_success` chose. Null when only
     * the centre took it, or nothing did.
     */
    public function channel(): ?string
    {
        foreach ($this->channels as $channel) {
            if (Channels::SITE !== $channel) {
                return $channel;
            }
        }

        return null;
    }

    /**
     * Whether a later attempt may reach the recipient: no channel took the notification, and at least one was refused
     * or held back by an open circuit.
     */
    public function isRetryable(): bool
    {
        if (DeliveryStatus::Failed !== $this->status) {
            return false;
        }

        return array_any($this->skipped, static fn (SkipReason $reason): bool => $reason->isTransient());
    }

    /**
     * What a log line or a metric may carry: the type, the mode, the status, the channels and the reasons, never the
     * recipient.
     *
     * @return array{type: string, mode: string, status: string, channel: string, channels: string, skipped: string}
     */
    public function context(): array
    {
        $skipped = [];

        foreach ($this->skipped as $channel => $reason) {
            $skipped[] = $channel.' ('.$reason->value.')';
        }

        return [
            'type' => $this->type,
            'mode' => $this->mode->value,
            'status' => $this->status->value,
            'channel' => $this->channel() ?? 'none',
            'channels' => [] === $this->channels ? 'none' : implode(', ', $this->channels),
            'skipped' => [] === $skipped ? 'none' : implode(', ', $skipped),
        ];
    }
}
