<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\WebPush;

use ErrorException;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Override;
use Phil\NotificationsBundle\Application\Contract\PushSubscriptions;
use Phil\NotificationsBundle\Application\Contract\WebPushSender;
use Phil\NotificationsBundle\Application\Delivery\ChannelOutcome;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\NotificationsBundle\Application\RenderedNotification;
use Phil\NotificationsBundle\Application\WebPushMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttplugClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The `web_push` channel on minishlink/web-push: the notification, as `WebPushMessage::of()` gives it, is encrypted for
 * each browser the recipient subscribed (RFC 8291), signed with the application's VAPID keys (RFC 8292) and posted to
 * its push service with Symfony HttpClient, redirections refused: a push service accepts it with a 2xx status. A
 * subscription its push service no longer knows — `404` or `410` — is forgotten; any other answer, or none, is logged
 * with the push service's host, never the endpoint, which is a secret of its browser.
 *
 * The notification is `Accepted` when one push service took it, `Unreachable` when the recipient has no subscription
 * or only forgotten ones, and `Refused` otherwise.
 *
 * Nothing is kept between two notifications: each one gets its own queue, so that a worker serves the next request
 * clean.
 */
final readonly class MinishlinkWebPushSender implements WebPushSender
{
    /** How long a push service has to answer, in seconds. */
    public const float TIMEOUT = 10.0;

    private Psr18Client $client;

    private HttplugClient $asyncClient;

    public function __construct(
        private NotificationTypes $types,
        private PushSubscriptions $subscriptions,
        private VapidKeys $vapid,
        HttpClientInterface $http,
        private int $ttl = WebPushMessage::TTL,
        private ?LoggerInterface $logger = null,
        float $timeout = self::TIMEOUT,
    ) {
        $http = $http->withOptions(['max_redirects' => 0, 'timeout' => $timeout]);
        $this->client = new Psr18Client($http);
        $this->asyncClient = new HttplugClient($http);
    }

    /**
     * @throws ErrorException when the library refuses the VAPID keys or a payload
     */
    #[Override]
    public function send(string $recipient, RenderedNotification $notification): ChannelOutcome
    {
        $subscriptions = $this->subscriptions->of($recipient);

        if ([] === $subscriptions) {
            return ChannelOutcome::Unreachable;
        }

        $message = WebPushMessage::of($notification, $this->types->get($notification->notification->type), $this->ttl);
        $push = new WebPush(
            ['VAPID' => ['subject' => $this->vapid->subject, 'publicKey' => $this->vapid->publicKey, 'privateKey' => $this->vapid->privateKey]],
            [],
            $this->client,
            $this->client,
            $this->client,
            $this->asyncClient,
            $this->logger,
        );
        $push->setReuseVAPIDHeaders(true);

        // The library leaves out an option that is null, as the topic of a message that collapses nothing.
        $options = ['TTL' => $message->ttl, 'urgency' => $message->urgency, 'topic' => $message->topic];
        $hosts = [];
        $outcome = ChannelOutcome::Unreachable;

        foreach ($subscriptions as $subscription) {
            $hosts[$subscription->endpoint] = $subscription->host;
            $push->queueNotification(
                new Subscription($subscription->endpoint, $subscription->publicKey, $subscription->authToken, $subscription->contentEncoding),
                $message->payload,
                $options,
            );
        }

        foreach ($push->flush() as $report) {
            if (!$report instanceof MessageSentReport) {
                continue;
            }

            $status = $report->getResponse()?->getStatusCode();

            // The library takes a redirection for a success; a push service accepts a message with a 2xx status only.
            if (null !== $status && $status >= 200 && $status < 300) {
                $outcome = ChannelOutcome::Accepted;

                continue;
            }

            $endpoint = $report->getEndpoint();

            if ($report->isSubscriptionExpired()) {
                $this->subscriptions->forget($endpoint);

                continue;
            }

            if (ChannelOutcome::Unreachable === $outcome) {
                $outcome = ChannelOutcome::Refused;
            }

            $this->logger?->warning('The push service {host} refused a notification of type {type}: {status}.', [
                'host' => $hosts[$endpoint] ?? 'unknown',
                'type' => $notification->notification->type,
                'status' => $status ?? 'no answer',
            ]);
        }

        return $outcome;
    }
}
