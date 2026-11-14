<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Infrastructure\Webhook;

use Override;
use Phil\FormsBundle\Application\EntryNotifier;
use Phil\FormsBundle\Application\NotificationFailed;
use Phil\FormsBundle\Domain\Model\Entry;
use Phil\StandardWebhooks\HmacSecret;
use Phil\StandardWebhooks\Signer;
use Phil\StandardWebhooks\WebhookId;
use Phil\StandardWebhooks\WebhookTimestamp;
use Psr\Clock\ClockInterface;
use RuntimeException;
use SensitiveParameter;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Posts an entry to its form's webhook as a Standard Webhooks message — `type` `form.entry.submitted`, the form's key,
 * the entry's id, when it came, its values —, signed with the configured secret, following no redirect. The values of
 * sensitive fields are left out, their names then listed under `withheld`. The webhook id is the entry's, so that the
 * receiver's deduplication absorbs a second delivery.
 */
final readonly class WebhookNotifier implements EntryNotifier
{
    public const string TYPE = 'form.entry.submitted';

    private HmacSecret $secret;

    public function __construct(
        private HttpClientInterface $client,
        #[SensitiveParameter]
        string $secret,
        private ClockInterface $clock,
        private float $timeout = 10.0,
    ) {
        $this->secret = HmacSecret::fromString($secret);
    }

    #[Override]
    public function notify(Entry $entry): void
    {
        $url = $entry->form->webhookUrl();

        if (null === $url) {
            return;
        }

        $values = $entry->values;
        $withheld = [];

        foreach ($entry->form->fields() as $field) {
            if ($field->sensitive && \array_key_exists($field->name, $values)) {
                unset($values[$field->name]);
                $withheld[] = $field->name;
            }
        }

        $now = $this->clock->now();
        $data = [
            'form' => $entry->form->key,
            'entry' => $entry->id,
            'submitted_at' => $entry->submittedAt()->format('Y-m-d\TH:i:s\Z'),
            'locale' => $entry->locale,
            'values' => $values,
        ];

        if ([] !== $withheld) {
            $data['withheld'] = $withheld;
        }

        $body = json_encode([
            'type' => self::TYPE,
            'timestamp' => $now->format('Y-m-d\TH:i:s.v\Z'),
            'data' => $data,
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        $signed = new Signer()->sign(WebhookId::fromString('msg_'.$entry->id), WebhookTimestamp::fromDateTime($now), $body, $this->secret);

        try {
            $status = $this->client->request('POST', $url, [
                'headers' => ['Content-Type' => 'application/json', ...$signed->toArray()],
                'body' => $body,
                'max_redirects' => 0,
                'timeout' => $this->timeout,
            ])->getStatusCode();
        } catch (TransportExceptionInterface $transportException) {
            throw NotificationFailed::because('webhook', $transportException);
        }

        if ($status < 200 || $status > 299) {
            throw NotificationFailed::because('webhook', new RuntimeException(\sprintf('%s answered %d.', $url, $status)));
        }
    }
}
