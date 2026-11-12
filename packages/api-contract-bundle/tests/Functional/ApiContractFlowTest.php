<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\ApiContractBundle\PhilApiContractBundle;
use Phil\ApiContractBundle\Tests\Functional\App\ApiController;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requests through the kernel: problems under `/api/`, an order created once per key and replayed, a conflict, a
 * failure forgotten, and the deprecation headers.
 */
#[CoversClass(PhilApiContractBundle::class)]
final class ApiContractFlowTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $connection = $this->service('doctrine.dbal.default_connection', Connection::class);
        foreach (new SchemaTool($entityManager)->getCreateSchemaSql([]) as $sql) {
            $connection->executeStatement($sql);
        }
    }

    public function testAnOrderIsCreatedOncePerKey(): void
    {
        $first = $this->post('/api/orders', '{"item":"a"}', 'key-1');
        $again = $this->post('/api/orders', '{"item":"a"}', 'key-1');

        self::assertSame([201, '{"order":1}', '/api/orders/1', null], [$first->getStatusCode(), $first->getContent(), $first->headers->get('Location'), $first->headers->get('Idempotent-Replayed')]);
        self::assertSame([201, '{"order":1}', '/api/orders/1', 'true', null], [$again->getStatusCode(), $again->getContent(), $again->headers->get('Location'), $again->headers->get('Idempotent-Replayed'), $again->headers->get('X-Trace')]);
        self::assertSame(1, $this->controller()->orders);

        $reused = $this->post('/api/orders', '{"item":"b"}', 'key-1');
        self::assertSame([422, 'application/problem+json'], [$reused->getStatusCode(), $reused->headers->get('Content-Type')]);
        self::assertSame(['type' => 'about:blank', 'status' => 422, 'title' => 'This Idempotency-Key was used with another request.'], json_decode((string) $reused->getContent(), true));

        self::assertSame(201, $this->post('/api/orders', '{"item":"a"}', 'key-2')->getStatusCode());
        self::assertSame(2, $this->controller()->orders);
    }

    public function testAKeyIsRequiredAndAFailureIsForgotten(): void
    {
        $missing = $this->post('/api/orders', '{}', null);
        self::assertSame([400, 'An Idempotency-Key header is required.'], [$missing->getStatusCode(), $this->title($missing)]);

        $failed = $this->post('/api/failing', '{}', 'key-3');
        self::assertSame([503, '30', 'Try later'], [$failed->getStatusCode(), $failed->headers->get('Retry-After'), $this->title($failed)]);
        self::assertSame(503, $this->post('/api/failing', '{}', 'key-3')->getStatusCode());
        self::assertSame(2, $this->controller()->orders);
    }

    public function testExceptionsUnderTheApiAreProblems(): void
    {
        $missing = $this->handle(Request::create('/api/missing'));
        self::assertSame([404, 'application/problem+json', ['type' => 'about:blank', 'status' => 404, 'title' => 'Not Found']], [$missing->getStatusCode(), $missing->headers->get('Content-Type'), json_decode((string) $missing->getContent(), true)]);

        $page = $this->handle(Request::create('/page'));
        self::assertSame(404, $page->getStatusCode());
        self::assertNotSame('application/problem+json', $page->headers->get('Content-Type'));
    }

    public function testADeprecatedEndpointSaysSo(): void
    {
        $response = $this->handle(Request::create('/api/old'));

        self::assertSame(['@1793491200', 'Sat, 01 May 2027 00:00:00 GMT', '<https://example.com/changes>; rel="deprecation"'], [$response->headers->get('Deprecation'), $response->headers->get('Sunset'), $response->headers->get('Link')]);
        self::assertNull($this->handle(Request::create('/api/missing'))->headers->get('Deprecation'));
    }

    public function testAReplayStillAnnouncesTheDeprecation(): void
    {
        $first = $this->post('/api/old-orders', '{}', 'key-4');
        $again = $this->post('/api/old-orders', '{}', 'key-4');

        self::assertSame([201, null, '@1793491200'], [$first->getStatusCode(), $first->headers->get('Idempotent-Replayed'), $first->headers->get('Deprecation')]);
        self::assertSame([201, 'true', '@1793491200', 'Sat, 01 May 2027 00:00:00 GMT'], [$again->getStatusCode(), $again->headers->get('Idempotent-Replayed'), $again->headers->get('Deprecation'), $again->headers->get('Sunset')]);
        self::assertSame(1, $this->controller()->orders);
    }

    private function post(string $path, string $body, ?string $key): Response
    {
        $request = Request::create($path, 'POST', content: $body);

        if (null !== $key) {
            $request->headers->set('Idempotency-Key', '"'.$key.'"');
        }

        return $this->handle($request);
    }

    private function handle(Request $request): Response
    {
        self::assertNotNull(self::$kernel);

        return self::$kernel->handle($request);
    }

    private function title(?Response $response): mixed
    {
        $problem = json_decode((string) $response?->getContent(), true);
        self::assertIsArray($problem);

        return $problem['title'] ?? null;
    }

    private function controller(): ApiController
    {
        return $this->service(ApiController::class, ApiController::class);
    }
}
