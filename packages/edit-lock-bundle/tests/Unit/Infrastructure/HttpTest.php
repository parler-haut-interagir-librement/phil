<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Unit\Infrastructure;

use Phil\EditLockBundle\Application\BlockSaves;
use Phil\EditLockBundle\Application\EditLocks;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\EditLockBundle\Domain\ValueObject\LeaseKey;
use Phil\EditLockBundle\Infrastructure\Http\ClientConfig;
use Phil\EditLockBundle\Infrastructure\Http\EditLockController;
use Phil\EditLockBundle\Infrastructure\Http\EditLockRequest;
use Phil\EditLockBundle\Infrastructure\Http\Routes;
use Phil\EditLockBundle\Infrastructure\Memory\InMemoryLeaseRepository;
use Phil\EditLockBundle\Tests\Unit\Fixture\FixedHolder;
use Phil\EditLockBundle\Tests\Unit\Fixture\Policy;
use Phil\EditLockBundle\Tests\Unit\Fixture\RacingRepository;
use Phil\EditLockBundle\Tests\Unit\Fixture\RecordingBroadcaster;
use Phil\EditLockBundle\Tests\Unit\Fixture\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Security\Csrf\SameOriginCsrfTokenManager;

#[CoversClass(EditLockController::class)]
#[CoversClass(EditLockRequest::class)]
#[CoversClass(ClientConfig::class)]
#[CoversClass(Routes::class)]
final class HttpTest extends TestCase
{
    private MockClock $clock;

    private EditLocks $locks;

    private RecordingBroadcaster $told;

    private FixedHolder $holder;

    private const string TOKEN = 'a-token-of-24-characters';

    private EditLockController $controller;

    private RequestStack $requests;

    private EditLockRequest $guard;

    protected function setUp(): void
    {
        $this->requests = new RequestStack();
        $this->guard = new EditLockRequest(new SameOriginCsrfTokenManager($this->requests, null, null, [EditLockRequest::TOKEN_ID]));
        $this->clock = new MockClock('2026-11-06 10:00:00 UTC');
        $this->told = new RecordingBroadcaster();
        $this->locks = new EditLocks(new InMemoryLeaseRepository(), $this->clock, 30, new Policy(), $this->told);
        $this->holder = new FixedHolder(Holder::of('bob', 'Bob'));
        $this->controller = new EditLockController($this->locks, new BlockSaves($this->locks, $this->told), $this->clock, $this->guard, $this->holder, true);
    }

    public function testALeaseThroughTheEndpoints(): void
    {
        self::assertSame([200, ['lease' => $this->lease('b1', 'bob', 'Bob', 30, true)]], $this->call('acquire', ['resource' => 'page', 'part' => 'b1']));
        $this->clock->sleep(10);
        self::assertSame([200, ['lease' => $this->lease('b1', 'bob', 'Bob', 30, true)]], $this->call('heartbeat', ['resource' => 'page', 'part' => 'b1']));
        $this->clock->sleep(5);

        $this->holder->holder = Holder::of('cy', 'Cy');
        self::assertSame([409, ['error' => 'held', 'lease' => $this->lease('b1', 'bob', 'Bob', 25, false)]], $this->call('acquire', ['resource' => 'page', 'part' => 'b1']));
        self::assertSame([200, ['leases' => [$this->lease('b1', 'bob', 'Bob', 25, false)]]], $this->call('presence', query: ['resource' => 'page']));
        self::assertSame([403, ['error' => 'refused']], $this->call('takeOver', ['resource' => 'page', 'part' => 'b1']));
        self::assertSame([409, ['error' => 'lost']], $this->call('heartbeat', ['resource' => 'page', 'part' => 'b1']));
        self::assertSame([204, ''], $this->call('release', ['resource' => 'page', 'part' => 'b1']));
        self::assertNotNull($this->locks->presence('page')[0] ?? null);

        $this->holder->holder = Holder::of('ada', 'Ada');
        self::assertSame([200, ['lease' => $this->lease('b1', 'ada', 'Ada', 30, true)]], $this->call('takeOver', ['resource' => 'page', 'part' => 'b1']));
        self::assertSame([200, ['lease' => $this->lease(null, 'ada', 'Ada', 30, true)]], $this->call('acquire', ['resource' => 'page']));
        self::assertSame([204, ''], $this->call('release', ['resource' => 'page', 'part' => 'b1']));
        self::assertSame([200, ['leases' => [$this->lease(null, 'ada', 'Ada', 30, true)]]], $this->call('presence', '', ['resource' => 'page']), 'the block released, presence asked without a body');
        self::assertSame([204, ''], $this->call('leave', ['resource' => 'page']));
        self::assertSame([], $this->locks->presence('page'));
    }

    public function testBlocksThroughTheEndpoints(): void
    {
        $this->locks->acquire(LeaseKey::of('page', 'b1'), Holder::of('bob', 'Bob'));
        $this->told->told = [];

        self::assertSame([204, ''], $this->call('save', ['resource' => 'page', 'block' => ['id' => 'b1', 'type' => 'paragraph', 'data' => ['text' => 'x']], 'list' => 't1:0', 'after' => 'b0']));
        self::assertSame([204, ''], $this->call('save', ['resource' => 'page', 'block' => ['id' => 'b1', 'type' => 'paragraph', 'data' => ['text' => 'y']]]));
        self::assertSame([204, ''], $this->call('remove', ['resource' => 'page', 'id' => 'b1']));
        self::assertSame([
            'saved page by bob in t1:0 after b0 {"blocks":[{"id":"b1","type":"paragraph","data":{"text":"x"}}]}',
            'saved page by bob in root after - {"blocks":[{"id":"b1","type":"paragraph","data":{"text":"y"}}]}',
            'removed page by bob b1',
        ], $this->told->told);

        self::assertSame([409, ['error' => 'lost']], $this->call('save', ['resource' => 'page', 'block' => ['id' => 'b2', 'type' => 'paragraph', 'data' => ['text' => 'x']]]));
        self::assertSame([409, ['error' => 'lost']], $this->call('remove', ['resource' => 'page', 'id' => 'b2']));

        foreach ([
            ['save', ['resource' => 'page', 'block' => ['id' => 'b1', 'type' => 'paragraph', 'data' => ['text' => 'x']], 'list' => 5], 'The list and the block before are texts.'],
            ['save', ['resource' => 'page', 'block' => ['id' => 'b1', 'type' => 'paragraph', 'data' => ['text' => 'x']], 'after' => 5], 'The list and the block before are texts.'],
            ['save', ['resource' => 'page'], 'The block is refused: The UJO document is invalid at blocks[0]: a block must be an object.'],
            ['save', ['block' => []], 'A lease is asked for by its resource and, optionally, its part, as texts.'],
            ['remove', ['resource' => 'page'], 'A block is removed by its identifier.'],
            ['remove', ['resource' => 'page', 'id' => 'b 1'], 'A part is named by 1 to 64 letters, digits, "_" or "-".'],
        ] as [$endpoint, $body, $message]) {
            self::assertSame([400, ['error' => 'invalid', 'message' => $message]], $this->call($endpoint, $body), $message);
        }
    }

    public function testTheEndpointsAnswerOnlyTheController(): void
    {
        foreach (['acquire', 'heartbeat', 'release', 'takeOver', 'leave', 'presence', 'save', 'remove'] as $endpoint) {
            self::assertSame([403, ['error' => 'forbidden']], $this->call($endpoint, ['resource' => 'page'], trusted: false), $endpoint);
            self::assertSame([403, ['error' => 'forbidden']], $this->call($endpoint, ['resource' => 'page'], site: 'cross-site'), $endpoint);
        }

        self::assertSame(200, $this->call('acquire', ['resource' => 'page'], site: 'same-origin')[0]);
        self::assertSame(200, $this->call('acquire', ['resource' => 'page'], site: 'same-origin', token: null)[0], 'the browser naming this site, without the cookie');
        self::assertSame(403, $this->call('acquire', ['resource' => 'page'], site: 'same-site')[0], 'another site of the same domain');
        self::assertSame(403, $this->call('acquire', ['resource' => 'page'], token: null)[0], 'a token without its cookie');
        self::assertSame(403, $this->call('acquire', ['resource' => 'page'], token: 'another-token-of-24-chars')[0], 'the cookie of another token');
        self::assertSame(403, $this->call('acquire', ['resource' => 'page'], token: false)[0], 'no token');
        self::assertSame(200, $this->call('presence', query: ['resource' => 'page'], token: false)[0], 'a read carries no token');
        self::assertSame(200, $this->call('presence', query: ['resource' => 'page'], site: 'same-origin', token: null)[0], 'a read without any cookie');

        $request = new Request();
        $request->headers->set(EditLockRequest::HEADER, 'yes');

        $this->requests->push($request);
        self::assertFalse($this->guard->isTrusted($request));
        self::assertSame(['X-Phil-Edit-Lock', 'phil_edit_lock', 'csrf-token'], [EditLockRequest::HEADER, EditLockRequest::TOKEN_ID, $this->guard->cookieName()]);
    }

    public function testTheEndpointsNeedAHolderAndThePolicy(): void
    {
        $this->holder->holder = null;
        self::assertSame([401, ['error' => 'anonymous']], $this->call('acquire', ['resource' => 'page']));

        foreach ([
            new EditLockController($this->locks, new BlockSaves($this->locks, $this->told), $this->clock, $this->guard, $this->holder),
            new EditLockController($this->locks, new BlockSaves($this->locks, $this->told), $this->clock, $this->guard, null, true),
        ] as $controller) {
            $this->controller = $controller;
            self::assertSame([404, ['error' => 'disabled']], $this->call('acquire', ['resource' => 'page']));
        }
    }

    public function testErrorsAreAnswered(): void
    {
        $this->holder->holder = Holder::of('eve', 'Eve');
        self::assertSame([403, ['error' => 'refused']], $this->call('acquire', ['resource' => 'page']));
        self::assertSame([403, ['error' => 'refused']], $this->call('presence', query: ['resource' => 'page']));

        $this->holder->holder = Holder::of('bob', 'Bob');
        self::assertSame([400, ['error' => 'invalid', 'message' => 'Could not decode request body.']], $this->call('acquire', '{nope'));
        self::assertSame([400, ['error' => 'invalid', 'message' => 'A resource is named by 1 to 255 bytes without control characters.']], $this->call('presence'));

        foreach ([['part' => 'b1'], ['resource' => 5], ['resource' => 'page', 'part' => 5], ['resource' => 'page', 'part' => 'b 1']] as $body) {
            self::assertSame(400, $this->call('acquire', $body)[0]);
        }

        $locks = new EditLocks(new RacingRepository(9), $this->clock, 30, new Policy());
        $this->controller = new EditLockController($locks, new BlockSaves($locks, $this->told), $this->clock, $this->guard, $this->holder, true);
        self::assertSame([409, ['error' => 'held', 'lease' => null]], $this->call('acquire', ['resource' => 'page']));
    }

    public function testTheControllerIsConfiguredForAResource(): void
    {
        $holder = Holder::of('bob', 'Bob');
        $hub = new MockHub('https://hub.test/.well-known/mercure', new StaticTokenProvider('x.y.z'), static fn (): string => 'id', publicUrl: 'https://example.test/.well-known/mercure');
        $urls = [
            'acquire' => '/_el/phil_edit_lock_acquire',
            'heartbeat' => '/_el/phil_edit_lock_heartbeat',
            'release' => '/_el/phil_edit_lock_release',
            'takeOver' => '/_el/phil_edit_lock_take_over',
            'leave' => '/_el/phil_edit_lock_leave',
            'presence' => '/_el/phil_edit_lock_presence',
            'save' => '/_el/phil_edit_lock_save',
            'remove' => '/_el/phil_edit_lock_remove',
        ];

        self::assertSame([
            'resource' => 'article:4 2',
            'holder' => ['id' => 'bob', 'name' => 'Bob'],
            'seconds' => 20,
            'takeOver' => true,
            'urls' => $urls,
            'topic' => 'urn:x:article:4 2',
            'hub' => 'https://example.test/.well-known/mercure?match=urn%3Ax%3Aarticle%3A4%202',
            'csrfCookie' => 'csrf-token',
        ], new ClientConfig(20, new Router(), $hub, 'urn:x:', $this->guard)->for('article:4 2', $holder, true));
        self::assertSame(
            'https://hub.test/m?jwt=1&topic=page',
            new ClientConfig(20, null, new MockHub('https://hub.test/m?jwt=1', new StaticTokenProvider('x.y.z'), static fn (): string => 'id', protocolVersion: ProtocolVersion::Legacy))->for('page', $holder)['hub'],
        );
        self::assertSame(
            ['resource' => 'page', 'holder' => ['id' => 'bob', 'name' => 'Bob'], 'seconds' => 30, 'takeOver' => false, 'urls' => null, 'topic' => null, 'hub' => null, 'csrfCookie' => null],
            new ClientConfig(30)->for('page', $holder),
        );
    }

    /**
     * @return array{resource: string, part: ?string, holder: array{id: string, name: string}, expiresIn: int, mine: bool}
     */
    private function lease(?string $part, string $id, string $name, int $expiresIn, bool $mine): array
    {
        return ['resource' => 'page', 'part' => $part, 'holder' => ['id' => $id, 'name' => $name], 'expiresIn' => $expiresIn, 'mine' => $mine];
    }

    /**
     * A request of the controller, made the current one where the token manager reads it: its header unless
     * `$trusted` is false, the token in the header and the same token's cookie, the cookie of another token when
     * `$token` names it, no cookie when it is null, no token at all when it is false.
     *
     * @param array<string, mixed>|string $body
     * @param array<string, string>       $query
     *
     * @return array{int, mixed}
     */
    private function call(string $endpoint, array|string $body = [], array $query = [], bool $trusted = true, ?string $site = null, string|false|null $token = self::TOKEN): array
    {
        $cookies = \is_string($token) ? ['csrf-token_'.$token => 'csrf-token'] : [];
        $request = new Request($query, [], [], $cookies, [], ['REQUEST_METHOD' => 'presence' === $endpoint ? 'GET' : 'POST'], \is_string($body) ? $body : json_encode($body, \JSON_THROW_ON_ERROR));
        $this->requests->push($request);

        if ($trusted) {
            $request->headers->set(EditLockRequest::HEADER, '1');
        }

        if (false !== $token) {
            $request->headers->set('csrf-token', self::TOKEN);
        }

        if (null !== $site) {
            $request->headers->set('Sec-Fetch-Site', $site);
        }

        $response = match ($endpoint) {
            'acquire' => $this->controller->acquire($request),
            'heartbeat' => $this->controller->heartbeat($request),
            'release' => $this->controller->release($request),
            'takeOver' => $this->controller->takeOver($request),
            'leave' => $this->controller->leave($request),
            'presence' => $this->controller->presence($request),
            'save' => $this->controller->save($request),
            default => $this->controller->remove($request),
        };
        self::assertInstanceOf(Response::class, $response);
        $content = (string) $response->getContent();

        if (Response::HTTP_NO_CONTENT === $response->getStatusCode()) {
            return [204, $content];
        }

        self::assertInstanceOf(JsonResponse::class, $response);

        return [$response->getStatusCode(), json_decode($content, true, 512, \JSON_THROW_ON_ERROR)];
    }
}
