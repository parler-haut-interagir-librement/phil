<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Phil\CommerceSyndicationBundle\Application\Inbox;
use Phil\CommerceSyndicationBundle\Infrastructure\Http\HttpClientTransport;
use Phil\CommerceSyndicationBundle\Infrastructure\Http\SyndicationWebhookController;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\InMemorySyndication;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\Partners;
use Phil\StandardWebhooks\Signer;
use Phil\StandardWebhooks\WebhookId;
use Phil\StandardWebhooks\WebhookTimestamp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(SyndicationWebhookController::class)]
#[CoversClass(HttpClientTransport::class)]
final class HttpTest extends TestCase
{
    public function testAnAppliedMessageIsFlushedAndAcknowledged(): void
    {
        [$controller, $store] = $this->controller(1);

        $response = $controller($this->request('msg_1', '{"type":"product.updated","timestamp":"2026-11-03T09:00:00.000000+00:00","audience":"shop","data":{"sku":"TEA-1","name":"Tea","currency":"CAD","retail":100,"cost":70,"tax_category":"food","available":1,"active":true}}'), 'supplier');

        self::assertSame(204, $response->getStatusCode());
        self::assertNotNull($store->findProduct('supplier', 'TEA-1'));
    }

    public function testAnUnknownPartnerIsNotFound(): void
    {
        [$controller] = $this->controller(0);

        self::assertSame(404, $controller($this->request('msg_1', '{"type":"x.y","audience":"shop"}'), 'nobody')->getStatusCode());
    }

    public function testARefusedMessageIsABadRequest(): void
    {
        [$controller] = $this->controller(0);

        self::assertSame(400, $controller($this->request('msg_1', '{"type":"order.forwarded","audience":"shop","data":{}}'), 'supplier')->getStatusCode());
        self::assertSame(400, $controller(Request::create('/', 'POST', content: '{}'), 'supplier')->getStatusCode());
        self::assertSame(400, $controller($this->request('msg_1', '{"type":"x.y","audience":"elsewhere"}'), 'supplier')->getStatusCode());
    }

    public function testTheTransportPostsAndAnswersTheStatus(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $headers = $options['headers'] ?? null;
            $requests[] = [$method, $url, $options['body'], $options['max_redirects'], $options['timeout'], \is_array($headers) && \in_array('webhook-id: msg_1', $headers, true)];

            return new MockResponse('', ['http_code' => 202]);
        });

        self::assertSame(202, new HttpClientTransport($client)->send('https://reseller.example/in', ['webhook-id' => 'msg_1'], '{}'));
        self::assertSame([['POST', 'https://reseller.example/in', '{}', 0, 10.0, true]], $requests);
    }

    public function testNoAnswerIsZero(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['error' => 'Connection refused']));

        self::assertSame(0, new HttpClientTransport($client)->send('https://reseller.example/in', [], '{}'));
    }

    /**
     * @return array{SyndicationWebhookController, InMemorySyndication}
     */
    private function controller(int $flushes): array
    {
        $store = new InMemorySyndication();
        $store->addPartner(Partners::supplier());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly($flushes))->method('flush');
        $inbox = new Inbox($store, $store, $store, $store, $store, 'shop', 300);

        return [new SyndicationWebhookController($inbox, $entityManager, new MockClock('2026-11-03 10:00:00')), $store];
    }

    private function request(string $id, string $body): Request
    {
        $headers = new Signer()->sign(WebhookId::fromString($id), WebhookTimestamp::fromDateTime(new DateTimeImmutable('2026-11-03 10:00:00')), $body, Partners::supplierKey())->toArray();
        $server = [];

        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return Request::create('/syndication/supplier', 'POST', server: $server, content: $body);
    }
}
