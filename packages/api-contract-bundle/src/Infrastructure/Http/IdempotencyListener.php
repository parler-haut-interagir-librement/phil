<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Http;

use LogicException;
use Phil\ApiContract\Problem\ProblemDetails;
use Phil\Idempotency\Contract\IdempotencyStore;
use Phil\Idempotency\Exception\InvalidIdempotencyValue;
use Phil\Idempotency\Exception\MissingIdempotencyKey;
use Phil\Idempotency\IdempotencyGuard;
use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyOutcome;
use Phil\Idempotency\PayloadFingerprint;
use Phil\Idempotency\StoredResponse;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Runs the idempotency guard of `phil/idempotency` around a controller marked `#[Idempotent]`: no key is a `400`; a
 * key used with another request a `422`, as the IETF draft of the header answers; a key whose first request is still
 * running a `409` with `Retry-After`, the seconds left on its reservation; a key already answered gets its response
 * again, marked `Idempotent-Replayed: true`. Every refusal is an RFC 9457 problem. A new key is reserved before the
 * controller, and its response kept after it — or the key marked failed, when the response is a `5xx` or cannot be
 * kept, so that the client may retry.
 *
 * The reservation expires after `$reservationTtl` seconds (or the key's own retention, if shorter): a process that
 * dies before its response blocks retries for that long only. The answered key is then kept for its retention, `$ttl`
 * or the controller's `#[Idempotent(ttl: …)]`.
 */
final readonly class IdempotencyListener
{
    public const string REPLAYED_HEADER = 'Idempotent-Replayed';

    public const string RESERVED = '_phil_api_contract_idempotency';

    /** The `detail` of an unreadable key: fixed, never the exception's message. */
    public const string KEY_FORMAT = 'An idempotency key is 1 to 255 printable ASCII characters, without quotes, backslashes or surrounding spaces.';

    private ?IdempotencyGuard $guard;

    /**
     * @param list<string> $replayedHeaders the response headers kept and replayed
     */
    public function __construct(
        ?IdempotencyStore $store,
        private IdempotencyScopeResolver $scopes,
        private int $ttl,
        ClockInterface $clock,
        private array $replayedHeaders = ['Content-Type', 'Location'],
        int $reservationTtl = 300,
    ) {
        $this->guard = $store instanceof IdempotencyStore ? new IdempotencyGuard($store, $clock, $ttl, $reservationTtl) : null;
    }

    public function onController(ControllerEvent $event): void
    {
        $attributes = $event->getAttributes(Idempotent::class);

        if ([] === $attributes) {
            return;
        }

        if (!$this->guard instanceof IdempotencyGuard) {
            throw new LogicException('An #[Idempotent] controller needs an IdempotencyStore: set phil_api_contract.idempotency.connection or cache_pool, or alias the port to a store of the application.');
        }

        $request = $event->getRequest();

        try {
            $key = IdempotencyKey::fromHeader($request->headers->get(IdempotencyKey::HEADER));
        } catch (MissingIdempotencyKey $missingIdempotencyKey) {
            throw ProblemException::of(Response::HTTP_BAD_REQUEST, 'An Idempotency-Key header is required.', previous: $missingIdempotencyKey);
        } catch (InvalidIdempotencyValue $invalidIdempotencyValue) {
            throw ProblemException::of(Response::HTTP_BAD_REQUEST, 'The Idempotency-Key header cannot be read.', self::KEY_FORMAT, $invalidIdempotencyValue);
        }

        $scope = $this->scopes->scope($request);
        $ttl = $attributes[0]->ttl ?? $this->ttl;
        $fingerprint = PayloadFingerprint::ofRequest($request->getMethod(), $request->getRequestUri(), $request->getContent());
        $decision = $this->guard->begin($scope, $key, $fingerprint, $ttl);

        if (IdempotencyOutcome::Reused === $decision->outcome) {
            throw ProblemException::of(Response::HTTP_UNPROCESSABLE_ENTITY, 'This Idempotency-Key was used with another request.');
        }

        if (IdempotencyOutcome::InProgress === $decision->outcome) {
            $inProgress = $this->guard->inProgress($decision);

            throw new ProblemException(ProblemDetails::create(Response::HTTP_CONFLICT, title: 'A request with this Idempotency-Key is in progress.'), ['Retry-After' => (string) $inProgress->retryAfter], $inProgress);
        }

        if (IdempotencyOutcome::Replay === $decision->outcome) {
            $replay = $decision->replay();
            $event->setController(static fn (): Response => self::replay($replay));

            return;
        }

        $request->attributes->set(self::RESERVED, new IdempotencyReservation($scope, $key, $ttl));
    }

    public function onResponse(ResponseEvent $event): void
    {
        $reserved = $event->getRequest()->attributes->get(self::RESERVED);

        if (!$reserved instanceof IdempotencyReservation || !$this->guard instanceof IdempotencyGuard) {
            return;
        }

        $event->getRequest()->attributes->remove(self::RESERVED);
        [$scope, $key] = [$reserved->scope, $reserved->key];

        $response = $event->getResponse();
        $content = $response->getContent();

        if (false === $content || $response->getStatusCode() >= Response::HTTP_INTERNAL_SERVER_ERROR) {
            $this->guard->fail($scope, $key, $reserved->ttl);

            return;
        }

        $headers = [];

        foreach ($this->replayedHeaders as $name) {
            $value = $response->headers->get($name);

            if (null !== $value) {
                $headers[$name] = [$value];
            }
        }

        $this->guard->complete($scope, $key, StoredResponse::of($response->getStatusCode(), $headers, $content), $reserved->ttl);
    }

    private static function replay(?StoredResponse $stored): Response
    {
        $stored ??= StoredResponse::nothing();

        return new Response($stored->body, $stored->status, [...$stored->headers, self::REPLAYED_HEADER => 'true']);
    }
}
