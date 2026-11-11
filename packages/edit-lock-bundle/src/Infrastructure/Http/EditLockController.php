<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Infrastructure\Http;

use Closure;
use Phil\EditLockBundle\Application\BlockSaves;
use Phil\EditLockBundle\Application\EditLocks;
use Phil\EditLockBundle\Application\Exception\EditRefused;
use Phil\EditLockBundle\Application\Exception\InvalidBlock;
use Phil\EditLockBundle\Application\Exception\LeaseHeld;
use Phil\EditLockBundle\Application\Exception\LeaseLost;
use Phil\EditLockBundle\Application\HolderProvider;
use Phil\EditLockBundle\Domain\Exception\InvalidLease;
use Phil\EditLockBundle\Domain\Model\Lease;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\EditLockBundle\Domain\ValueObject\LeaseKey;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The endpoints of the collaboration controller. Each answers only a request of the controller, from a holder the
 * application names, when the application has configured its holder provider and its policy; the body is JSON:
 * `{"resource", "part"}` for leases, `{"resource", "block", "list", "after"}` to save a block, `{"resource", "id"}` to
 * remove one. A lease is answered as `{"resource", "part", "holder": {"id", "name"}, "expiresIn", "mine"}`.
 */
final readonly class EditLockController
{
    public function __construct(
        private EditLocks $locks,
        private BlockSaves $blocks,
        private ClockInterface $clock,
        private EditLockRequest $requests,
        private ?HolderProvider $holders = null,
        private bool $enabled = false,
    ) {
    }

    public function acquire(Request $request): Response
    {
        return $this->lease($request, fn (LeaseKey $key, Holder $holder): Lease => $this->locks->acquire($key, $holder));
    }

    public function heartbeat(Request $request): Response
    {
        return $this->lease($request, fn (LeaseKey $key, Holder $holder): Lease => $this->locks->heartbeat($key, $holder));
    }

    public function takeOver(Request $request): Response
    {
        return $this->lease($request, fn (LeaseKey $key, Holder $holder): Lease => $this->locks->takeOver($key, $holder));
    }

    public function release(Request $request): Response
    {
        return $this->handle($request, function (Holder $holder, array $body): Response {
            $this->locks->release($this->key($body), $holder);

            return new Response(null, Response::HTTP_NO_CONTENT);
        });
    }

    public function leave(Request $request): Response
    {
        return $this->handle($request, function (Holder $holder, array $body): Response {
            $this->locks->leave($this->key($body)->resource, $holder);

            return new Response(null, Response::HTTP_NO_CONTENT);
        });
    }

    public function presence(Request $request): Response
    {
        return $this->handle($request, function (Holder $holder) use ($request): Response {
            $key = LeaseKey::of($request->query->getString('resource'));

            if (!$this->locks->mayEdit($holder, $key->resource)) {
                throw EditRefused::edit($key);
            }

            return new JsonResponse(['leases' => array_map(fn (Lease $lease): array => $this->json($lease, $holder), $this->locks->presence($key->resource))]);
        }, false);
    }

    public function save(Request $request): Response
    {
        return $this->handle($request, function (Holder $holder, array $body): Response {
            $list = $body['list'] ?? 'root';
            $after = $body['after'] ?? null;

            if (!\is_string($list) || (null !== $after && !\is_string($after))) {
                throw InvalidBlock::because('The list and the block before are texts.');
            }

            $this->blocks->save($this->key($body)->resource, $holder, $body['block'] ?? null, $list, $after);

            return new Response(null, Response::HTTP_NO_CONTENT);
        });
    }

    public function remove(Request $request): Response
    {
        return $this->handle($request, function (Holder $holder, array $body): Response {
            $id = $body['id'] ?? null;

            if (!\is_string($id)) {
                throw InvalidBlock::because('A block is removed by its identifier.');
            }

            $this->blocks->remove($this->key($body)->resource, $holder, $id);

            return new Response(null, Response::HTTP_NO_CONTENT);
        });
    }

    /**
     * @param Closure(LeaseKey, Holder): Lease $change
     */
    private function lease(Request $request, Closure $change): Response
    {
        return $this->handle($request, fn (Holder $holder, array $body): Response => new JsonResponse(['lease' => $this->json($change($this->key($body), $holder), $holder)]));
    }

    /**
     * Runs an endpoint for the current holder, its errors answered with their status.
     *
     * @param Closure(Holder, array<array-key, mixed>): Response $endpoint
     */
    private function handle(Request $request, Closure $endpoint, bool $body = true): Response
    {
        if (!$this->enabled || !$this->holders instanceof HolderProvider) {
            return $this->error(Response::HTTP_NOT_FOUND, 'disabled');
        }

        if (!$this->requests->isTrusted($request)) {
            return $this->error(Response::HTTP_FORBIDDEN, 'forbidden');
        }

        $holder = $this->holders->holder();

        if (!$holder instanceof Holder) {
            return $this->error(Response::HTTP_UNAUTHORIZED, 'anonymous');
        }

        try {
            return $endpoint($holder, $body ? $request->toArray() : []);
        } catch (JsonException|InvalidLease|InvalidBlock $exception) {
            return $this->error(Response::HTTP_BAD_REQUEST, 'invalid', ['message' => $exception->getMessage()]);
        } catch (EditRefused) {
            return $this->error(Response::HTTP_FORBIDDEN, 'refused');
        } catch (LeaseHeld $leaseHeld) {
            return $this->error(Response::HTTP_CONFLICT, 'held', ['lease' => $leaseHeld->lease instanceof Lease ? $this->json($leaseHeld->lease, $holder) : null]);
        } catch (LeaseLost) {
            return $this->error(Response::HTTP_CONFLICT, 'lost');
        }
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function key(array $body): LeaseKey
    {
        $resource = $body['resource'] ?? null;
        $part = $body['part'] ?? null;

        if (!\is_string($resource) || (null !== $part && !\is_string($part))) {
            throw InvalidLease::because('A lease is asked for by its resource and, optionally, its part, as texts.');
        }

        return LeaseKey::of($resource, $part);
    }

    /**
     * @return array{resource: string, part: ?string, holder: array{id: string, name: string}, expiresIn: int, mine: bool}
     */
    private function json(Lease $lease, Holder $holder): array
    {
        return [
            'resource' => $lease->key()->resource,
            'part' => $lease->key()->part,
            'holder' => ['id' => $lease->holder()->id, 'name' => $lease->holder()->name],
            'expiresIn' => $lease->secondsLeftAt($this->clock->now()),
            'mine' => $lease->isHeldBy($holder),
        ];
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function error(int $status, string $error, array $extra = []): JsonResponse
    {
        return new JsonResponse(['error' => $error, ...$extra], $status);
    }
}
