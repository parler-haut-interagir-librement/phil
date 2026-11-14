<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Contract;

use Phil\NotificationsBundle\Application\RenderedNotification;

/**
 * Sends a notification to the browsers a recipient subscribed for Web Push: the application keeps the subscriptions and
 * its VAPID keys, and signs (RFC 8292) and encrypts (RFC 8291) each message. `WebPushMessage::of()` gives what to send
 * — the payload its service worker shows, the urgency, the topic and the time to live —, so that an implementation
 * only encrypts it for each subscription and posts it to the push service.
 */
interface WebPushSender
{
    public function send(string $recipient, RenderedNotification $notification): void;
}
