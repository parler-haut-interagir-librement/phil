<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Webhook;

use Override;
use Phil\Automations\Contract\WebhookKeys;
use Phil\Automations\Definition\AutomationId;
use Phil\Automations\Definition\Options;
use Phil\Automations\Engine\ActionContext;
use Phil\Automations\Engine\Payload;
use Phil\Automations\Run\RunId;
use Phil\Automations\Webhook\WebhookAction;
use Phil\StandardWebhooks\HmacSecret;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(WebhookAction::class)]
final class WebhookBodyTest extends TestCase
{
    public function testAnEmptyPayloadAndNoOutputsAreEmptyObjects(): void
    {
        $body = $this->bodyOf('42', Payload::empty(), []);

        self::assertStringContainsString('"payload":{},"outputs":{}', $body);
    }

    public function testSlashesAndLettersStayReadable(): void
    {
        $body = $this->bodyOf('é/x', Payload::of(['path' => 'a/é']), []);

        self::assertStringContainsString('"subject":"é/x"', $body);
        self::assertStringContainsString('"path":"a/é"', $body);
    }

    public function testTheIdOfAStepIsDrawnFromItsKey(): void
    {
        self::assertSame('msg_'.substr(hash('sha256', 'run/step'), 0, 32), WebhookAction::id('run/step')->value);
    }

    /**
     * @param array<string, Payload> $outputs
     */
    private function bodyOf(?string $subject, Payload $payload, array $outputs): string
    {
        $sent = '';
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $body = $options['body'] ?? '';
            $sent = \is_string($body) ? $body : '';

            return new MockResponse('', ['http_code' => 204]);
        });
        $secret = HmacSecret::fromString('whsec_'.base64_encode(str_repeat('k', 32)));
        $keys = new readonly class($secret) implements WebhookKeys {
            public function __construct(
                private HmacSecret $secret,
            ) {
            }

            #[Override]
            public function signingKeys(string $name): array
            {
                return [$this->secret];
            }
        };

        new WebhookAction($client, $keys, new MockClock('2026-11-13 10:00:00 UTC'), allowPrivateNetworks: true)->execute(new ActionContext(
            RunId::fromString(str_repeat('a', 32)),
            AutomationId::fromString('welcome'),
            'notify',
            Options::of(['url' => 'https://hooks.example.com/in', 'event' => 'member.welcomed', 'key' => 'main']),
            $payload,
            $subject,
            $outputs,
            'key',
        ));

        return $sent;
    }
}
