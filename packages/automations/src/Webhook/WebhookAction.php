<?php

declare(strict_types=1);

namespace Phil\Automations\Webhook;

use Override;
use Phil\Automations\Catalogue\ComponentDescription;
use Phil\Automations\Catalogue\OptionSpec;
use Phil\Automations\Catalogue\OptionType;
use Phil\Automations\Contract\Action;
use Phil\Automations\Contract\WebhookKeys;
use Phil\Automations\Engine\ActionContext;
use Phil\Automations\Engine\Payload;
use Phil\Automations\Exception\ActionFailed;
use Phil\StandardWebhooks\Signer;
use Phil\StandardWebhooks\WebhookId;
use Phil\StandardWebhooks\WebhookTimestamp;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The action `webhook`: a JSON `POST` signed as Standard Webhooks asks, to an `https` address, with the run's
 * identifiers.
 *
 * ```json
 * {"type": "member.welcomed", "timestamp": "2026-11-13T10:00:00+00:00",
 *  "data": {"automation": "welcome", "run": "…", "step": "notify", "subject": "42", "payload": {…}, "outputs": {…}}}
 * ```
 *
 * Every attempt of one step of one run sends the same `webhook-id`, drawn from the step's idempotency key, so that the
 * receiver's deduplication absorbs a retry. The address is read with `phil/url` — no user name, no IP address, no
 * single-label host — and the HTTP client is wrapped in Symfony's `NoPrivateNetworkHttpClient`, which refuses a host
 * that resolves inside the private network; no redirect is followed. A `2xx` answer succeeds, a `408`, `425`, `429`,
 * `5xx` or no answer is worth retrying, any other answer fails for good.
 *
 * The guard keeps the addresses it resolved: `reset()`, which a container calls between the messages of a worker,
 * forgets them.
 */
final readonly class WebhookAction implements Action, ResetInterface
{
    public const string TYPE = 'webhook';

    private HttpClientInterface $client;

    public function __construct(
        HttpClientInterface $client,
        private WebhookKeys $keys,
        private ClockInterface $clock,
        private Signer $signer = new Signer(),
        private float $timeout = 10.0,
        bool $allowPrivateNetworks = false,
    ) {
        $this->client = $allowPrivateNetworks ? $client : new NoPrivateNetworkHttpClient($client);
    }

    #[Override]
    public function reset(): void
    {
        if ($this->client instanceof ResetInterface) {
            $this->client->reset();
        }
    }

    #[Override]
    public function describe(): ComponentDescription
    {
        return new ComponentDescription(self::TYPE, 'Send a signed webhook', [
            new OptionSpec('url', OptionType::Url, true, 'Receiver address'),
            new OptionSpec('event', OptionType::Identifier, true, 'Event type'),
            new OptionSpec('key', OptionType::Identifier, true, 'Signing key name'),
        ]);
    }

    #[Override]
    public function execute(ActionContext $context): Payload
    {
        $url = $this->url($context->options->string('url') ?? '');
        $keys = $this->keys->signingKeys($context->options->string('key') ?? '');

        if ([] === $keys) {
            throw ActionFailed::permanent('The webhook names a signing key the application does not hold.');
        }

        $now = $this->clock->now();
        $body = json_encode([
            'type' => $context->options->string('event'),
            'timestamp' => $now->format(\DATE_RFC3339),
            'data' => [
                'automation' => $context->automationId->value,
                'run' => $context->runId->value,
                'step' => $context->stepId,
                'subject' => $context->subject,
                'payload' => (object) $context->payload->values,
                'outputs' => (object) array_map(static fn (Payload $output): object => (object) $output->values, $context->outputs),
            ],
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        $headers = $this->signer->sign(self::id($context->idempotencyKey), WebhookTimestamp::fromDateTime($now), $body, ...$keys);

        try {
            $status = $this->client->request('POST', $url, [
                'headers' => ['Content-Type' => 'application/json', ...$headers->toArray()],
                'body' => $body,
                'max_redirects' => 0,
                'timeout' => $this->timeout,
            ])->getStatusCode();
        } catch (TransportExceptionInterface $transportException) {
            throw ActionFailed::retryable('The receiver did not answer.', $transportException);
        }

        if ($status >= 200 && $status < 300) {
            return Payload::of(['status' => $status, 'webhook_id' => $headers->id->value]);
        }

        $message = \sprintf('The receiver answered %d.', $status);

        throw \in_array($status, [408, 425, 429], true) || $status >= 500 ? ActionFailed::retryable($message) : ActionFailed::permanent($message);
    }

    /**
     * The `webhook-id` of every attempt of one step of one run.
     */
    public static function id(string $idempotencyKey): WebhookId
    {
        return WebhookId::fromString('msg_'.substr(hash('sha256', $idempotencyKey), 0, 32));
    }

    private function url(string $value): string
    {
        try {
            $url = Url::fromString($value);
        } catch (InvalidUrl $invalidUrl) {
            throw ActionFailed::permanent('The webhook address is not a web address.', $invalidUrl);
        }

        if ('https' !== $url->scheme) {
            throw ActionFailed::permanent('The webhook address is not an https address.');
        }

        return $url->toString();
    }
}
