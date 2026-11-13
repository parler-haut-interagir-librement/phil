<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Webhook;

use LogicException;
use Override;
use Phil\Automations\Catalogue\OptionSpec;
use Phil\Automations\Contract\WebhookKeys;
use Phil\Automations\Definition\AutomationId;
use Phil\Automations\Definition\Options;
use Phil\Automations\Engine\ActionContext;
use Phil\Automations\Engine\Payload;
use Phil\Automations\Exception\ActionFailed;
use Phil\Automations\Run\RunId;
use Phil\Automations\Webhook\WebhookAction;
use Phil\StandardWebhooks\HmacSecret;
use Phil\StandardWebhooks\SignatureList;
use Phil\StandardWebhooks\SignedContent;
use Phil\StandardWebhooks\WebhookId;
use Phil\StandardWebhooks\WebhookTimestamp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(WebhookAction::class)]
final class WebhookActionTest extends TestCase
{
    private HmacSecret $secret;

    protected function setUp(): void
    {
        $this->secret = HmacSecret::fromString('whsec_'.base64_encode(str_repeat('k', 32)));
    }

    public function testItPostsASignedBodyWithTheRunsIdentifiers(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options];

            return new MockResponse('', ['http_code' => 204]);
        });

        $result = $this->action($client)->execute($this->context());

        self::assertCount(1, $requests);
        [$method, $url, $options] = $requests[0];
        self::assertSame('POST', $method);
        self::assertSame('https://hooks.example.com/in', $url);
        self::assertSame(0, $options['max_redirects']);
        self::assertEqualsWithDelta(4.0, $options['timeout'], 0.001);
        $body = $options['body'];
        self::assertIsString($body);
        self::assertSame('{"type":"member.welcomed","timestamp":"2026-11-13T10:00:00+00:00","data":{"automation":"welcome","run":"'.str_repeat('a', 32).'","step":"notify","subject":"42","payload":{"order":"7"},"outputs":{"add":{"created":true},"none":{}}}}', $body);
        $headers = [];
        self::assertIsArray($options['headers']);

        foreach ($options['headers'] as $header) {
            self::assertIsString($header);
            [$name, $value] = explode(': ', $header, 2);
            $headers[strtolower($name)] = $value;
        }

        self::assertSame('application/json', $headers['content-type']);
        self::assertSame(WebhookAction::id(str_repeat('a', 32).'/notify')->value, $headers['webhook-id']);
        self::assertSame((string) new MockClock('2026-11-13 10:00:00 UTC')->now()->getTimestamp(), $headers['webhook-timestamp']);
        $content = SignedContent::of(WebhookId::fromString($headers['webhook-id']), WebhookTimestamp::fromHeader($headers['webhook-timestamp']), $body);
        self::assertTrue($this->secret->verifiesAny($content, SignatureList::fromHeader($headers['webhook-signature'])));
        self::assertSame(['status' => 204, 'webhook_id' => $headers['webhook-id']], $result->values);
    }

    public function testEveryAttemptOfAStepSendsTheSameId(): void
    {
        self::assertSame(WebhookAction::id('a/b')->value, WebhookAction::id('a/b')->value);
        self::assertNotSame(WebhookAction::id('a/b')->value, WebhookAction::id('a/c')->value);
        self::assertMatchesRegularExpression('/^msg_[0-9a-f]{32}$/', WebhookAction::id('a/b')->value);
    }

    #[DataProvider('answers')]
    public function testAnAnswerOutside2xxFails(int $status, bool $retryable): void
    {
        try {
            $this->action(new MockHttpClient(new MockResponse('', ['http_code' => $status])))->execute($this->context());
            self::fail('Expected a failure.');
        } catch (ActionFailed $actionFailed) {
            self::assertSame($retryable, $actionFailed->retryable);
            self::assertSame(\sprintf('The receiver answered %d.', $status), $actionFailed->getMessage());
        }
    }

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function answers(): iterable
    {
        yield '300' => [300, false];
        yield '400' => [400, false];
        yield '404' => [404, false];
        yield '408' => [408, true];
        yield '425' => [425, true];
        yield '429' => [429, true];
        yield '499' => [499, false];
        yield '500' => [500, true];
        yield '503' => [503, true];
    }

    public function testA2xxAnswerSucceeds(): void
    {
        self::assertSame(299, $this->action(new MockHttpClient(new MockResponse('', ['http_code' => 299])))->execute($this->context())->get('status'));
        self::assertSame(200, $this->action(new MockHttpClient(new MockResponse('', ['http_code' => 200])))->execute($this->context())->get('status'));
    }

    public function testNoAnswerIsWorthRetrying(): void
    {
        try {
            $this->action(new MockHttpClient(new MockResponse('', ['error' => 'refused'])))->execute($this->context());
            self::fail('Expected a failure.');
        } catch (ActionFailed $actionFailed) {
            self::assertTrue($actionFailed->retryable);
            self::assertSame('The receiver did not answer.', $actionFailed->getMessage());
            self::assertNotNull($actionFailed->getPrevious());
        }
    }

    /**
     * @param array<string, string> $options
     */
    #[DataProvider('refusedOptions')]
    public function testItRefusesForGood(array $options, string $message): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => throw new LogicException('No request is sent.'));

        try {
            $this->action($client)->execute($this->context($options));
            self::fail('Expected a failure.');
        } catch (ActionFailed $actionFailed) {
            self::assertFalse($actionFailed->retryable);
            self::assertSame($message, $actionFailed->getMessage());
        }
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function refusedOptions(): iterable
    {
        yield 'not an address' => [['url' => 'nowhere', 'key' => 'main'], 'The webhook address is not a web address.'];
        yield 'an IP address' => [['url' => 'https://127.0.0.1/in', 'key' => 'main'], 'The webhook address is not a web address.'];
        yield 'plain http' => [['url' => 'http://hooks.example.com/in', 'key' => 'main'], 'The webhook address is not an https address.'];
        yield 'an unknown key' => [['url' => 'https://hooks.example.com/in', 'key' => 'other'], 'The webhook names a signing key the application does not hold.'];
    }

    public function testTheClientIsGuardedUnlessPrivateNetworksAreAllowed(): void
    {
        $guarded = new WebhookAction(new MockHttpClient(), $this->keys(), new MockClock());
        $open = new WebhookAction($client = new MockHttpClient(), $this->keys(), new MockClock(), allowPrivateNetworks: true);

        self::assertInstanceOf(NoPrivateNetworkHttpClient::class, new ReflectionProperty(WebhookAction::class, 'client')->getValue($guarded));
        self::assertSame($client, new ReflectionProperty(WebhookAction::class, 'client')->getValue($open));
        $guarded->reset();
        $open->reset();
    }

    public function testResetForgetsTheResolvedAddresses(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 204]));
        $action = $this->action($client);
        $action->execute($this->context());

        $action->reset();

        self::assertSame(0, $client->getRequestsCount());
    }

    public function testItDescribesItsOptions(): void
    {
        $description = $this->action(new MockHttpClient())->describe();

        self::assertSame('webhook', $description->type);
        self::assertSame(['url', 'event', 'key'], array_map(static fn (OptionSpec $spec): string => $spec->name, $description->options));
        self::assertFalse($description->compensable);
        self::assertSame([], $description->problems(Options::of(['url' => 'https://a.example', 'event' => 'e', 'key' => 'k'])));
        self::assertCount(3, $description->problems(Options::none()));
    }

    private function action(MockHttpClient $client): WebhookAction
    {
        return new WebhookAction($client, $this->keys(), new MockClock('2026-11-13 10:00:00 UTC'), timeout: 4.0, allowPrivateNetworks: true);
    }

    private function keys(): WebhookKeys
    {
        $secret = $this->secret;

        return new readonly class($secret) implements WebhookKeys {
            public function __construct(
                private HmacSecret $secret,
            ) {
            }

            #[Override]
            public function signingKeys(string $name): array
            {
                return 'main' === $name ? [$this->secret] : [];
            }
        };
    }

    /**
     * @param array<string, string> $options
     */
    private function context(array $options = ['url' => 'https://hooks.example.com/in', 'event' => 'member.welcomed', 'key' => 'main']): ActionContext
    {
        return new ActionContext(
            RunId::fromString(str_repeat('a', 32)),
            AutomationId::fromString('welcome'),
            'notify',
            Options::of($options),
            Payload::of(['order' => '7']),
            '42',
            ['add' => Payload::of(['created' => true]), 'none' => Payload::empty()],
            str_repeat('a', 32).'/notify',
        );
    }
}
