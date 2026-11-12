<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Unit\Infrastructure;

use Closure;
use DateTimeImmutable;
use LogicException;
use Phil\ApiContract\Exception\InvalidContractValue;
use Phil\ApiContractBundle\Infrastructure\Http\CallerScopeResolver;
use Phil\ApiContractBundle\Infrastructure\Http\IdempotencyListener;
use Phil\ApiContractBundle\Infrastructure\Http\IdempotencyReservation;
use Phil\ApiContractBundle\Infrastructure\Http\Idempotent;
use Phil\ApiContractBundle\Infrastructure\Http\ProblemException;
use Phil\Idempotency\Exception\InvalidIdempotencyValue;
use Phil\Idempotency\Exception\RequestInProgress;
use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyRecord;
use Phil\Idempotency\IdempotencyScope;
use Phil\Idempotency\IdempotencyState;
use Phil\Idempotency\PayloadFingerprint;
use Phil\Idempotency\StoredResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(IdempotencyListener::class)]
#[CoversClass(Idempotent::class)]
#[CoversClass(ProblemException::class)]
#[CoversClass(IdempotencyReservation::class)]
final class IdempotencyListenerTest extends TestCase
{
    public function testANewKeyIsReservedThenAnswered(): void
    {
        $store = new InMemoryIdempotencyStore();
        $listener = self::listener($store);
        $request = self::request('k1');
        $event = self::controller($request, new Idempotent());

        $listener->onController($event);
        self::assertSame(['reserve ip:10.0.0.1|k1'], $store->calls);
        self::assertSame('2026-11-03T12:01:40+00:00', $store->expiresAt?->format('c'));

        $response = new Response('{"id":1}', Response::HTTP_CREATED, ['Location' => '/orders/1', 'Content-Type' => 'application/json', 'X-Trace' => 'abc']);
        $listener->onResponse($this->response($request, $response));
        $record = $store->find(IdempotencyScope::of('ip:10.0.0.1'), IdempotencyKey::fromString('k1'));
        self::assertSame([201, ['Location' => ['/orders/1'], 'Content-Type' => ['application/json']], '{"id":1}'], [$record?->response?->status, $record?->response?->headers, $record?->response?->body]);
        self::assertFalse($request->attributes->has('_phil_api_contract_idempotency'));
        self::assertSame('2026-11-03T12:01:40+00:00', $store->completedUntil?->format('c'));

        $listener->onResponse($this->response($request, $response));
        self::assertCount(2, $store->calls());
    }

    public function testAReservationExpiresSoonAndItsAnswerIsKeptForTheRetention(): void
    {
        $store = new InMemoryIdempotencyStore();
        $listener = self::listener($store, reservationTtl: 30);
        $request = self::request('k1');

        $listener->onController(self::controller($request, new Idempotent()));
        self::assertSame('2026-11-03T12:00:30+00:00', $store->expiresAt?->format('c'), 'A process that dies now blocks the key for 30 seconds, not for its retention.');

        $listener->onResponse($this->response($request, new Response('{}', Response::HTTP_CREATED)));
        self::assertSame('2026-11-03T12:01:40+00:00', $store->completedUntil?->format('c'));
    }

    public function testTheAttributeChangesTheLifetime(): void
    {
        $store = new InMemoryIdempotencyStore();
        $listener = self::listener($store);
        $request = self::request('k1');
        $listener->onController(self::controller($request, new Idempotent(ttl: 5)));
        self::assertSame('2026-11-03T12:00:05+00:00', $store->expiresAt?->format('c'), 'A retention shorter than the reservation bounds it too.');

        $listener->onResponse($this->response($request, new Response('{}')));
        self::assertSame('2026-11-03T12:00:05+00:00', $store->completedUntil?->format('c'));

        $long = new InMemoryIdempotencyStore();
        $listener = self::listener($long);
        $request = self::request('k2');
        $listener->onController(self::controller($request, new Idempotent(ttl: 7 * 86_400)));
        $listener->onResponse($this->response($request, new Response('{}')));
        self::assertSame(['2026-11-03T12:05:00+00:00', '2026-11-10T12:00:00+00:00'], [$long->expiresAt?->format('c'), $long->completedUntil?->format('c')]);
    }

    public function testAnAttributeTtlBelowOneSecondIsRefused(): void
    {
        foreach ([0, -1] as $ttl) {
            try {
                new Idempotent(ttl: $ttl);
                self::fail(\sprintf('A ttl of %d was accepted.', $ttl));
            } catch (InvalidContractValue $invalidContractValue) {
                self::assertSame(\sprintf('The ttl of #[Idempotent] is at least 1 second; %d is not.', $ttl), $invalidContractValue->getMessage());
            }
        }

        self::assertSame(1, new Idempotent(ttl: 1)->ttl);
    }

    public function testAKnownKeyIsReplayed(): void
    {
        $store = new InMemoryIdempotencyStore();
        $request = self::request('k1');
        $store->records['ip:10.0.0.1|k1'] = IdempotencyRecord::completed($this->fingerprint(), StoredResponse::of(201, ['Location' => ['/orders/1']], '{"id":1}'), new DateTimeImmutable('2026-11-04 UTC'));
        $event = self::controller($request, new Idempotent());

        self::listener($store)->onController($event);
        $response = ($event->getController())();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame([201, '{"id":1}', '/orders/1', 'true'], [$response->getStatusCode(), $response->getContent(), $response->headers->get('Location'), $response->headers->get(IdempotencyListener::REPLAYED_HEADER)]);
        self::assertSame([], $store->calls);
        self::assertFalse($request->attributes->has('_phil_api_contract_idempotency'), 'A replay holds no reservation for the response to complete.');
        self::assertSame('Idempotent-Replayed', IdempotencyListener::REPLAYED_HEADER);
    }

    public function testReusedKeysAndRequestsInProgress(): void
    {
        $store = new InMemoryIdempotencyStore();
        $request = self::request('k1');
        $store->records['ip:10.0.0.1|k1'] = IdempotencyRecord::inProgress(PayloadFingerprint::ofRequest('POST', '/other', ''), new DateTimeImmutable('2026-11-03 12:04:00 UTC'));
        $this->assertProblem(422, 'This Idempotency-Key was used with another request.', static fn () => self::listener($store)->onController(self::controller($request, new Idempotent())));

        $store->records['ip:10.0.0.1|k1'] = IdempotencyRecord::completed(PayloadFingerprint::ofRequest('POST', '/orders', '{"item":"b"}'), StoredResponse::of(201, [], '{"id":1}'), new DateTimeImmutable('2026-11-04 UTC'));
        $this->assertProblem(422, 'This Idempotency-Key was used with another request.', static fn () => self::listener($store)->onController(self::controller($request, new Idempotent())));

        $store->records['ip:10.0.0.1|k1'] = IdempotencyRecord::failed(PayloadFingerprint::ofRequest('PUT', '/orders', ''), new DateTimeImmutable('2026-11-04 UTC'));
        $this->assertProblem(422, 'This Idempotency-Key was used with another request.', static fn () => self::listener($store)->onController(self::controller($request, new Idempotent())));

        $store->records['ip:10.0.0.1|k1'] = IdempotencyRecord::inProgress($this->fingerprint(), new DateTimeImmutable('2026-11-03 12:00:42 UTC'));

        try {
            self::listener($store)->onController(self::controller($request, new Idempotent()));
            self::fail('A request in progress was let through.');
        } catch (ProblemException $problemException) {
            self::assertSame([409, 'A request with this Idempotency-Key is in progress.'], [$problemException->problem->status, $problemException->problem->title]);
            self::assertSame(['Retry-After' => '42'], $problemException->headers, 'The client retries once the reservation has expired.');
            self::assertInstanceOf(RequestInProgress::class, $problemException->getPrevious());
        }

        $racing = new InMemoryIdempotencyStore();
        $racing->reserves = false;
        $this->assertProblem(409, 'A request with this Idempotency-Key is in progress.', static fn () => self::listener($racing)->onController(self::controller(self::request('k2'), new Idempotent())));
        self::assertFalse(self::request('k2')->attributes->has('_phil_api_contract_idempotency'));
    }

    public function testAFailedRequestRunsAgainWithTheSamePayload(): void
    {
        $store = new InMemoryIdempotencyStore();
        $store->records['ip:10.0.0.1|k1'] = IdempotencyRecord::failed($this->fingerprint(), new DateTimeImmutable('2026-11-04 UTC'));
        $request = self::request('k1');

        self::listener($store)->onController(self::controller($request, new Idempotent()));

        self::assertSame(['reserve ip:10.0.0.1|k1'], $store->calls);
        self::assertInstanceOf(IdempotencyReservation::class, $request->attributes->get(IdempotencyListener::RESERVED));
    }

    public function testAMissingOrBadKeyIsABadRequest(): void
    {
        $store = new InMemoryIdempotencyStore();
        $this->assertProblem(400, 'An Idempotency-Key header is required.', static fn () => self::listener($store)->onController(self::controller(self::request(null), new Idempotent())));

        try {
            self::listener($store)->onController(self::controller(self::request("bad\x01"), new Idempotent()));
            self::fail('A bad key was read.');
        } catch (ProblemException $problemException) {
            self::assertSame([400, 'The Idempotency-Key header cannot be read.'], [$problemException->problem->status, $problemException->problem->title]);
            self::assertSame(IdempotencyListener::KEY_FORMAT, $problemException->problem->detail);
            self::assertSame('An idempotency key is 1 to 255 printable ASCII characters, without quotes, backslashes or surrounding spaces.', IdempotencyListener::KEY_FORMAT);
            self::assertInstanceOf(InvalidIdempotencyValue::class, $problemException->getPrevious(), 'The exception stays the previous one, for the log, never the detail.');
        }
    }

    public function testAFailureOrAStreamIsForgotten(): void
    {
        foreach ([new Response('', Response::HTTP_INTERNAL_SERVER_ERROR), new Response('', Response::HTTP_SERVICE_UNAVAILABLE), new StreamedResponse(static function (): void {})] as $response) {
            $store = new InMemoryIdempotencyStore();
            $listener = self::listener($store);
            $request = self::request('k1');
            $listener->onController(self::controller($request, new Idempotent()));
            $listener->onResponse($this->response($request, $response));

            self::assertSame(['reserve ip:10.0.0.1|k1', 'fail ip:10.0.0.1|k1'], $store->calls);
            self::assertSame(IdempotencyState::Failed, $store->records['ip:10.0.0.1|k1']->state);
            self::assertSame('2026-11-03T12:01:40+00:00', $store->completedUntil?->format('c'));
        }

        $store = new InMemoryIdempotencyStore();
        $listener = self::listener($store);
        $request = self::request('k1');
        $listener->onController(self::controller($request, new Idempotent()));
        $listener->onResponse($this->response($request, new Response('', 499)));
        self::assertSame('complete ip:10.0.0.1|k1', $store->calls[1]);
    }

    public function testAControllerWithoutTheAttributeIsLeftAlone(): void
    {
        $store = new InMemoryIdempotencyStore();
        $request = self::request(null);
        self::listener($store)->onController(self::controller($request, null));
        self::listener($store)->onResponse($this->response($request, new Response()));
        self::assertSame([], $store->calls);
        self::assertSame('_phil_api_contract_idempotency', IdempotencyListener::RESERVED);

        $request->attributes->set(IdempotencyListener::RESERVED, new IdempotencyReservation(IdempotencyScope::of('ip:10.0.0.1'), IdempotencyKey::fromString('k1'), 100));
        new IdempotencyListener(null, new CallerScopeResolver(), 100, new NativeClock())->onResponse($this->response($request, new Response()));
        self::assertTrue($request->attributes->has(IdempotencyListener::RESERVED));
    }

    public function testTheDefaultHeadersAreReplayed(): void
    {
        $store = new InMemoryIdempotencyStore();
        $listener = new IdempotencyListener($store, new CallerScopeResolver(), 100, new NativeClock());
        $request = self::request('k1');
        $listener->onController(self::controller($request, new Idempotent()));
        $listener->onResponse($this->response($request, new Response('', Response::HTTP_CREATED, ['Location' => '/o/1', 'Content-Type' => 'text/plain', 'X-Trace' => 'a'])));

        self::assertSame(['Content-Type' => ['text/plain'], 'Location' => ['/o/1']], $store->records['ip:10.0.0.1|k1']->response?->headers);
    }

    public function testAReservationLastsFiveMinutesByDefault(): void
    {
        $store = new InMemoryIdempotencyStore();
        $clock = new readonly class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-11-03 12:00:00 UTC');
            }
        };
        new IdempotencyListener($store, new CallerScopeResolver(), 3_600, $clock)->onController(self::controller(self::request('k1'), new Idempotent()));

        self::assertSame('2026-11-03T12:05:00+00:00', $store->expiresAt?->format('c'));
    }

    public function testWithoutAStoreTheAttributeCannotWork(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('An #[Idempotent] controller needs an IdempotencyStore');

        new IdempotencyListener(null, new CallerScopeResolver(), 100, new NativeClock())->onController(self::controller(self::request('k1'), new Idempotent()));
    }

    public function testWithoutAClockItIsNow(): void
    {
        $store = new InMemoryIdempotencyStore();
        new IdempotencyListener($store, new CallerScopeResolver(), 100, new NativeClock())->onController(self::controller(self::request('k1'), new Idempotent()));

        self::assertEqualsWithDelta(time() + 100, $store->expiresAt?->getTimestamp(), 5);
    }

    private function assertProblem(int $status, string $title, Closure $call): void
    {
        try {
            $call();
            self::fail($title);
        } catch (ProblemException $problemException) {
            self::assertSame([$status, $title, $status, $title], [$problemException->problem->status, $problemException->problem->title, $problemException->getCode(), $problemException->getMessage()]);
        }
    }

    private static function listener(InMemoryIdempotencyStore $store, int $reservationTtl = 300): IdempotencyListener
    {
        $clock = new readonly class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-11-03 12:00:00 UTC');
            }
        };

        return new IdempotencyListener($store, new CallerScopeResolver(), 100, $clock, ['Location', 'Content-Type'], $reservationTtl);
    }

    private static function request(?string $key): Request
    {
        $request = Request::create('/orders?x=1', 'POST', server: ['REMOTE_ADDR' => '10.0.0.1'], content: '{"item":"a"}');

        if (null !== $key) {
            $request->headers->set('Idempotency-Key', $key);
        }

        return $request;
    }

    private function fingerprint(): PayloadFingerprint
    {
        return PayloadFingerprint::ofRequest('POST', '/orders?x=1', '{"item":"a"}');
    }

    private static function controller(Request $request, ?Idempotent $attribute): ControllerEvent
    {
        $kernel = self::createStub(HttpKernelInterface::class);
        $event = new ControllerEvent($kernel, static fn (): Response => new Response(), $request, HttpKernelInterface::MAIN_REQUEST);
        $event->setController($event->getController(), $attribute instanceof Idempotent ? [$attribute] : []);

        return $event;
    }

    private function response(Request $request, Response $response): ResponseEvent
    {
        return new ResponseEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }
}
