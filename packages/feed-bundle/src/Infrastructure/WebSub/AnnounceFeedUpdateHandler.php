<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Infrastructure\WebSub;

use Phil\FeedBundle\Application\Contract\FeedUrls;
use Phil\FeedBundle\Application\Exception\UnknownFeed;
use Phil\FeedBundle\Application\Feeds;
use Phil\FeedBundle\Application\FeedWriters;
use Phil\FeedBundle\Infrastructure\WebSub\Exception\HubNotificationFailed;
use Phil\SafeHttp\Exception\RefusedAddress;
use Phil\SafeHttp\Exception\SafeHttpException;
use Phil\SafeHttp\SafeHttpClient;
use Phil\Url\Url;
use Psr\Log\LoggerInterface;

/**
 * Tells each hub that the feed changed, once per format served: a `POST` of `hub.mode=publish` and `hub.url`, the
 * topic, as the WebSub hubs read it, through `phil/safe-http`.
 *
 * - A hub that is, or resolves to, a private address is refused by `phil/safe-http` before any connection: the refusal
 *   is logged and not retried, since the address will not change.
 * - A hub that does not answer in time, or answers other than `2xx`, is logged; once every hub was tried,
 *   `HubNotificationFailed` hands the message back to Messenger for a retry.
 * - A feed that no longer exists is skipped.
 */
final readonly class AnnounceFeedUpdateHandler
{
    public const string REFUSED = 'The WebSub hub {hub} is refused: {reason}.';

    public const string FAILED = 'The WebSub hub {hub} did not take the update of {topic}: {reason}.';

    public const string UNKNOWN = 'The feed {feed} no longer exists: its update is not announced.';

    /**
     * @param list<Url> $hubs
     */
    public function __construct(
        private Feeds $feeds,
        private FeedWriters $writers,
        private FeedUrls $urls,
        private SafeHttpClient $client,
        private array $hubs,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(AnnounceFeedUpdate $message): void
    {
        try {
            $this->feeds->source($message->feed);
        } catch (UnknownFeed) {
            $this->logger?->info(self::UNKNOWN, ['feed' => $message->feed]);

            return;
        }

        $failed = [];

        foreach ($this->hubs as $hub) {
            $taken = true;

            foreach ($this->writers->formats() as $format) {
                $topic = $this->urls->feed($message->feed, $format)->toString();

                if (!$this->announce($hub, $topic)) {
                    $taken = false;
                }
            }

            if (!$taken) {
                $failed[] = $hub->toString();
            }
        }

        if ([] !== $failed) {
            throw HubNotificationFailed::at($message->feed, $failed);
        }
    }

    /**
     * Whether the hub needs no retry: it took the topic, or it is refused for good.
     */
    private function announce(Url $hub, string $topic): bool
    {
        try {
            $response = $this->client->send('POST', $hub, ['Content-Type' => 'application/x-www-form-urlencoded'], http_build_query(['hub.mode' => 'publish', 'hub.url' => $topic], '', '&', \PHP_QUERY_RFC3986));
        } catch (RefusedAddress $refusedAddress) {
            $this->logger?->warning(self::REFUSED, ['hub' => $hub->toString(), 'reason' => $refusedAddress->getMessage()]);

            return true;
        } catch (SafeHttpException $safeHttpException) {
            $this->logger?->warning(self::FAILED, ['hub' => $hub->toString(), 'topic' => $topic, 'reason' => $safeHttpException->getMessage()]);

            return false;
        }

        if ($response->isSuccessful()) {
            return true;
        }

        $this->logger?->warning(self::FAILED, ['hub' => $hub->toString(), 'topic' => $topic, 'reason' => 'status '.$response->status]);

        return false;
    }
}
