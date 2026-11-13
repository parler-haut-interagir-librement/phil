<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg;

use Phil\PagedMedia\Gotenberg\Internal\Url;

/**
 * Where Gotenberg sends a result instead of answering with it: it answers 204 at once, uploads the result to the URL,
 * then posts a `webhook.success` or `webhook.error` event to the events URL.
 */
final readonly class Webhook
{
    public string $url;

    public string $eventsUrl;

    /**
     * @param array<string, string> $extraHttpHeaders headers sent with the upload and the event, such as `Authorization`
     */
    public function __construct(
        string $url,
        string $eventsUrl,
        public WebhookMethod $method = WebhookMethod::Post,
        public array $extraHttpHeaders = [],
    ) {
        $this->url = Url::http($url, 'A webhook');
        $this->eventsUrl = Url::http($eventsUrl, 'A webhook events URL');
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        $headers = [
            'Gotenberg-Webhook-Url' => $this->url,
            'Gotenberg-Webhook-Events-Url' => $this->eventsUrl,
            'Gotenberg-Webhook-Method' => $this->method->value,
        ];

        if ([] !== $this->extraHttpHeaders) {
            $headers['Gotenberg-Webhook-Extra-Http-Headers'] = json_encode($this->extraHttpHeaders, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
        }

        return $headers;
    }
}
