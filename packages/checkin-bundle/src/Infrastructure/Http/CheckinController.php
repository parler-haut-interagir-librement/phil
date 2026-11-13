<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Infrastructure\Http;

use Closure;
use Phil\Checkin\CheckIn;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Credential;
use Phil\Checkin\CredentialCode;
use Phil\Checkin\DeviceId;
use Phil\Checkin\Direction;
use Phil\Checkin\Exception\InvalidCheckin;
use Phil\Checkin\Exception\InvalidOfflineBatch;
use Phil\Checkin\Exception\LedgerContention;
use Phil\Checkin\Exception\ManifestTooLarge;
use Phil\Checkin\Exception\WeakManifestCodes;
use Phil\Checkin\Offline\ManifestIssuer;
use Phil\Checkin\Offline\OfflineBatch;
use Phil\Checkin\Offline\OfflineScan;
use Phil\Checkin\Offline\OfflineSync;
use Phil\Checkin\Offline\SyncStatus;
use Phil\Checkin\ScanId;
use Phil\Checkin\ScanResult;
use Phil\CheckinBundle\Application\Event\CheckInRecorded;
use Phil\CheckinBundle\Infrastructure\Rules\CheckpointDesks;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The endpoints of the desk controller. Each answers only a request of the controller, when the application has
 * turned them on and its security grants the attribute {@see self::SCAN} on the checkpoint, a
 * `CheckpointId`:
 *
 * - `scan`, `POST {"code", "checkpoint", "device", "direction", "id"}`, answers the outcome, the credential, its
 *   attendance and whether the scan was replayed;
 * - `manifest`, `GET ?checkpoint=…&device=…`, answers the offline manifest, never cached;
 * - `sync`, `POST {"checkpoint", "device", "scans"}`, answers the status of each scan of the journal.
 *
 * The scans and manifests go through the desk of their checkpoint, with its admission rule; each scan recorded,
 * online or from a journal, is dispatched as {@see CheckInRecorded} when the application has an event dispatcher.
 *
 * Errors are `{"error"}`: `disabled` (404), `forbidden` (403), `invalid` (400, with the reason, which never repeats a
 * code), `too_large` (413), `weak_codes` (409, a manifest of codes too short to travel with its key) and `busy` (503,
 * to retry under the same scan identifier).
 */
final readonly class CheckinController
{
    /**
     * The attribute the application's security grants on a `CheckpointId` to whoever may check in there.
     */
    public const string SCAN = 'PHIL_CHECKIN_SCAN';

    public function __construct(
        private CheckpointDesks $desks,
        private CheckinRequest $requests,
        private ?AuthorizationCheckerInterface $authorization = null,
        private ?OfflineSync $sync = null,
        private int $batchLimit = OfflineBatch::LIMIT,
        private bool $enabled = false,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    public function scan(Request $request): Response
    {
        return $this->handle($request, true, function (array $body, CheckpointId $checkpoint): Response {
            $code = $body['code'] ?? null;
            $direction = $body['direction'] ?? Direction::In->value;
            $id = $body['id'] ?? null;

            if (!\is_string($code) || !\is_string($direction) || (null !== $id && !\is_string($id))) {
                throw InvalidCheckin::because('A scan has a "code", and may have a "direction" and an "id", all texts.');
            }

            $direction = Direction::tryFrom($direction) ?? throw InvalidCheckin::because('A direction is "in" or "out".');
            $result = $this->desks->desk($checkpoint, $direction)->scan(
                CredentialCode::of($code),
                $checkpoint,
                $this->device($body),
                $direction,
                null === $id ? null : ScanId::of($id),
            );

            if ($result->checkIn instanceof CheckIn && !$result->replayed) {
                $this->events?->dispatch(CheckInRecorded::online($result->checkIn));
            }

            return $this->noStore(new JsonResponse($this->result($result)));
        });
    }

    public function manifest(Request $request): Response
    {
        return $this->handle($request, false, function (array $body, CheckpointId $checkpoint): Response {
            $manifests = $this->desks->manifests($checkpoint);

            if (!$manifests instanceof ManifestIssuer) {
                return $this->error(Response::HTTP_NOT_FOUND, 'disabled');
            }

            return $this->noStore(new JsonResponse($manifests->issue($checkpoint, $this->device($body))->toArray()));
        });
    }

    public function sync(Request $request): Response
    {
        if (!$this->sync instanceof OfflineSync) {
            return $this->error(Response::HTTP_NOT_FOUND, 'disabled');
        }

        $sync = $this->sync;

        return $this->handle($request, true, function (array $body, CheckpointId $checkpoint) use ($sync): Response {
            $device = $this->device($body);
            $scans = OfflineBatch::read($body['scans'] ?? null, $this->batchLimit);
            $report = $sync->apply($checkpoint, $device, $scans)->toArray();
            $this->synchronised($scans, $report, $checkpoint, $device);

            return $this->noStore(new JsonResponse(['scans' => $report]));
        });
    }

    /**
     * Dispatches the scans of a journal the synchronisation recorded, with or without a conflict.
     *
     * @param list<OfflineScan>                       $scans
     * @param list<array{id: string, status: string}> $report
     */
    private function synchronised(array $scans, array $report, CheckpointId $checkpoint, DeviceId $device): void
    {
        if (!$this->events instanceof EventDispatcherInterface) {
            return;
        }

        $statuses = array_column($report, 'status', 'id');

        foreach ($scans as $scan) {
            $status = SyncStatus::tryFrom($statuses[$scan->id->value] ?? '');

            if (SyncStatus::Recorded === $status || SyncStatus::Conflict === $status) {
                $this->events->dispatch(CheckInRecorded::synchronised($scan, $checkpoint, $device, SyncStatus::Conflict === $status));
            }
        }
    }

    /**
     * Runs an endpoint on the body — or the query of a `GET` — once the request is trusted and the checkpoint granted,
     * its errors answered with their status.
     *
     * @param Closure(array<array-key, mixed>, CheckpointId): Response $endpoint
     */
    private function handle(Request $request, bool $body, Closure $endpoint): Response
    {
        if (!$this->enabled) {
            return $this->error(Response::HTTP_NOT_FOUND, 'disabled');
        }

        if (!$this->requests->isTrusted($request)) {
            return $this->error(Response::HTTP_FORBIDDEN, 'forbidden');
        }

        try {
            $data = $body ? $request->toArray() : $request->query->all();
            $checkpoint = $data['checkpoint'] ?? null;
            $checkpoint = CheckpointId::of(\is_string($checkpoint) ? $checkpoint : '');

            if (true !== $this->authorization?->isGranted(self::SCAN, $checkpoint)) {
                return $this->error(Response::HTTP_FORBIDDEN, 'forbidden');
            }

            return $endpoint($data, $checkpoint);
        } catch (JsonException) {
            return $this->error(Response::HTTP_BAD_REQUEST, 'invalid', 'The body is a JSON object.');
        } catch (InvalidCheckin|InvalidOfflineBatch $invalid) {
            return $this->error(Response::HTTP_BAD_REQUEST, 'invalid', $invalid->getMessage());
        } catch (ManifestTooLarge) {
            return $this->error(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, 'too_large');
        } catch (WeakManifestCodes) {
            return $this->error(Response::HTTP_CONFLICT, 'weak_codes');
        } catch (LedgerContention) {
            return $this->error(Response::HTTP_SERVICE_UNAVAILABLE, 'busy');
        }
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidCheckin
     */
    private function device(array $data): DeviceId
    {
        $device = $data['device'] ?? null;

        return DeviceId::of(\is_string($device) ? $device : '');
    }

    /**
     * @return array{outcome: string, replayed: bool, id: ?string, credential: ?array{id: string, label: ?string}, attendance: array{entries: int, inside: bool, lastEntryAt: ?int}}
     */
    private function result(ScanResult $result): array
    {
        return [
            'outcome' => $result->outcome->value,
            'replayed' => $result->replayed,
            'id' => $result->checkIn?->id->value,
            'credential' => $result->credential instanceof Credential ? ['id' => $result->credential->id, 'label' => $result->credential->label] : null,
            'attendance' => [
                'entries' => $result->attendance->entries,
                'inside' => $result->attendance->inside,
                'lastEntryAt' => $result->attendance->lastEntryAt?->getTimestamp(),
            ],
        ];
    }

    private function noStore(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    private function error(int $status, string $error, ?string $reason = null): Response
    {
        return $this->noStore(new JsonResponse(null === $reason ? ['error' => $error] : ['error' => $error, 'reason' => $reason], $status));
    }
}
