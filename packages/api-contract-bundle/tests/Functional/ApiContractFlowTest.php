<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\ApiContractBundle\PhilApiContractBundle;
use Phil\ApiContractBundle\Tests\Functional\App\ApiController;
use Phil\ApiContractBundle\Tests\Functional\App\OrderVoter;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requests through the kernel: problems under `/api/`, an order created once per key and replayed, a conflict, a
 * failure forgotten, the deprecation headers, keys of their own for each tenant and each user, the access checks of a
 * controller run again before a replay, and anonymous callers refused.
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

    public function testARequestApiPlatformHandlesIsLeftToIt(): void
    {
        $missing = $this->handle(Request::create('/api/books/missing'));
        self::assertSame(404, $missing->getStatusCode());
        self::assertNotSame('application/problem+json', $missing->headers->get('Content-Type'));

        $first = $this->post('/api/books', '{}', null);
        $again = $this->post('/api/books', '{}', 'key-9');
        self::assertSame([201, 201, null, null, null], [$first->getStatusCode(), $again->getStatusCode(), $first->headers->get('Deprecation'), $again->headers->get('Idempotent-Replayed'), $again->headers->get('Sunset')]);
        self::assertSame(2, $this->controller()->orders);

        $ours = $this->post('/api/old-orders', '{}', null);
        self::assertSame([400, 'application/problem+json'], [$ours->getStatusCode(), $ours->headers->get('Content-Type')]);
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

    /**
     * Two tenants share one store: the same user, key, path and body in the second tenant runs its own request,
     * instead of being answered with the first tenant's response.
     */
    public function testTwoTenantsOnOneStoreNeverShareAKey(): void
    {
        $acme = $this->post('/api/orders', '{"item":"a"}', 'key-5', host: 'acme.test');
        $globex = $this->post('/api/orders', '{"item":"a"}', 'key-5', host: 'globex.test');
        $acmeAgain = $this->post('/api/orders', '{"item":"a"}', 'key-5', host: 'acme.test');

        self::assertSame([201, '{"order":1}', null], [$acme->getStatusCode(), $acme->getContent(), $acme->headers->get('Idempotent-Replayed')]);
        self::assertSame([201, '{"order":2}', null], [$globex->getStatusCode(), $globex->getContent(), $globex->headers->get('Idempotent-Replayed')]);
        self::assertSame([201, '{"order":1}', 'true'], [$acmeAgain->getStatusCode(), $acmeAgain->getContent(), $acmeAgain->headers->get('Idempotent-Replayed')]);
        self::assertSame(2, $this->controller()->orders);
        self::assertSame(2, $this->keys());
    }

    public function testTwoUsersNeverShareAKey(): void
    {
        $ada = $this->post('/api/orders', '{"item":"a"}', 'key-6');
        $grace = $this->post('/api/orders', '{"item":"a"}', 'key-6', user: 'grace');

        self::assertSame(['{"order":1}', '{"order":2}', null], [$ada->getContent(), $grace->getContent(), $grace->headers->get('Idempotent-Replayed')]);
    }

    /**
     * The permission was revoked while the key was kept: the replay runs `#[IsGranted]` again and is refused, without
     * the body the first request was answered with.
     */
    public function testARevokedPermissionIsNotAnsweredWithTheStoredResponse(): void
    {
        $first = $this->post('/api/guarded-orders', '{"item":"a"}', 'key-7');
        self::assertSame([201, null], [$first->getStatusCode(), $first->headers->get('Idempotent-Replayed')]);
        self::assertStringContainsString('only for the permitted', (string) $first->getContent());

        $again = $this->post('/api/guarded-orders', '{"item":"a"}', 'key-7');
        self::assertSame([201, 'true', $first->getContent()], [$again->getStatusCode(), $again->headers->get('Idempotent-Replayed'), $again->getContent()]);

        $this->service(OrderVoter::class, OrderVoter::class)->revoked = ['ada'];
        $revoked = $this->post('/api/guarded-orders', '{"item":"a"}', 'key-7');

        self::assertSame(403, $revoked->getStatusCode());
        self::assertNull($revoked->headers->get('Idempotent-Replayed'));
        self::assertStringNotContainsString('only for the permitted', (string) $revoked->getContent());
        self::assertSame(1, $this->controller()->orders);
    }

    /**
     * A request refused by an access check holds no key: once granted, the same key runs the controller.
     */
    public function testARefusedRequestTakesNoKey(): void
    {
        $this->service(OrderVoter::class, OrderVoter::class)->revoked = ['ada'];
        self::assertSame(403, $this->post('/api/guarded-orders', '{}', 'key-8')->getStatusCode());
        self::assertSame(0, $this->keys());

        $this->service(OrderVoter::class, OrderVoter::class)->revoked = [];
        $granted = $this->post('/api/guarded-orders', '{}', 'key-8');
        self::assertSame([201, null], [$granted->getStatusCode(), $granted->headers->get('Idempotent-Replayed')]);
    }

    public function testAnAnonymousCallerIsRefused(): void
    {
        $anonymous = $this->post('/api/orders', '{"item":"a"}', 'key-9', user: null);

        self::assertSame([403, 'An Idempotency-Key is accepted from an authenticated caller only.'], [$anonymous->getStatusCode(), $this->title($anonymous)]);
        self::assertSame(0, $this->controller()->orders);
    }

    public function testAnEndpointThatAcceptsGuestsScopesThemByAddress(): void
    {
        $first = $this->post('/api/guest-orders', '{"item":"a"}', 'key-10', user: null, address: '192.0.2.7');
        $replay = $this->post('/api/guest-orders', '{"item":"a"}', 'key-10', user: null, address: '192.0.2.7');
        $elsewhere = $this->post('/api/guest-orders', '{"item":"a"}', 'key-10', user: null, address: '198.51.100.4');

        self::assertSame([201, null], [$first->getStatusCode(), $first->headers->get('Idempotent-Replayed')]);
        self::assertSame([201, 'true'], [$replay->getStatusCode(), $replay->headers->get('Idempotent-Replayed')]);
        self::assertSame([201, null], [$elsewhere->getStatusCode(), $elsewhere->headers->get('Idempotent-Replayed')], 'Another address has keys of its own.');
        self::assertSame(2, $this->controller()->orders);

        $scopes = $this->service('doctrine.dbal.default_connection', Connection::class)->fetchFirstColumn('SELECT scope FROM phil_idempotency');
        self::assertCount(2, $scopes);
        self::assertStringNotContainsString('192.0.2.7', var_export($scopes, true), 'The address is kept as part of a hash only.');

        self::assertSame(403, $this->post('/api/orders', '{"item":"a"}', 'key-11', user: null, address: '192.0.2.7')->getStatusCode(), 'The other endpoints still refuse guests.');
    }

    private function post(string $path, string $body, ?string $key, ?string $user = 'ada', string $host = 'localhost', string $address = '127.0.0.1'): Response
    {
        $server = ['HTTP_HOST' => $host, 'REMOTE_ADDR' => $address];

        if (null !== $user) {
            $server += ['PHP_AUTH_USER' => $user, 'PHP_AUTH_PW' => $user];
        }

        $request = Request::create($path, 'POST', server: $server, content: $body);

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

    private function keys(): int
    {
        $count = $this->service('doctrine.dbal.default_connection', Connection::class)->fetchOne('SELECT COUNT(*) FROM phil_idempotency');
        self::assertIsNumeric($count);

        return (int) $count;
    }

    private function controller(): ApiController
    {
        return $this->service(ApiController::class, ApiController::class);
    }
}
