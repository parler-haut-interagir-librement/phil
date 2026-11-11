<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Infrastructure\Http;

use DateTimeInterface;
use Phil\MonitoringBundle\Application\Contract\MonitoringStatus;
use Phil\MonitoringBundle\Domain\ValueObject\CheckResult;
use Phil\MonitoringBundle\Domain\ValueObject\HeartbeatStatus;
use Phil\MonitoringBundle\Domain\ValueObject\MaintenanceState;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The endpoints of external uptime monitors, in JSON and never cached:
 *
 * - liveness, `{"status": "up"}` while the application answers, without running a check;
 * - readiness, the overall status of the checks only, `503` when one is down;
 * - the detailed view, every check, scheduled task and the maintenance, for a bearer of the monitoring token only —
 *   `404` when no token is configured, `401` without the right one.
 *
 * Nothing in them carries more than names, statuses, counts and moments.
 */
final readonly class HealthController
{
    public const string LIVE_ROUTE = 'phil_monitoring_live';

    public const string READY_ROUTE = 'phil_monitoring_ready';

    public const string DETAILS_ROUTE = 'phil_monitoring_details';

    public function __construct(
        private MonitoringStatus $monitoring,
        private ?string $token = null,
    ) {
    }

    public function live(): JsonResponse
    {
        return $this->json(['status' => 'up'], Response::HTTP_OK);
    }

    public function ready(): JsonResponse
    {
        $status = $this->monitoring->health()->status();

        return $this->json(['status' => $status->value], $status->isAvailable() ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }

    public function details(Request $request): JsonResponse
    {
        if (null === $this->token || '' === $this->token) {
            throw new NotFoundHttpException();
        }

        if (!hash_equals('Bearer '.$this->token, (string) $request->headers->get('Authorization'))) {
            $response = $this->json(['status' => 'unauthorized'], Response::HTTP_UNAUTHORIZED);
            $response->headers->set('WWW-Authenticate', 'Bearer');

            return $response;
        }

        $report = $this->monitoring->health();
        $maintenance = $this->monitoring->maintenance();

        return $this->json([
            'status' => $report->status()->value,
            'checked_at' => $report->checkedAt->format(DateTimeInterface::ATOM),
            'checks' => array_map(static fn (CheckResult $result): array => [
                'name' => $result->name,
                'status' => $result->status()->value,
                'summary' => $result->outcome->summary,
                'duration_ms' => $result->durationMilliseconds,
            ], $report->results),
            'heartbeats' => array_map(static fn (HeartbeatStatus $status): array => [
                'task' => $status->task,
                'state' => $status->state->value,
                'last_beat_at' => $status->lastBeatAt?->format(DateTimeInterface::ATOM),
                'due_by' => $status->dueBy?->format(DateTimeInterface::ATOM),
            ], $this->monitoring->heartbeats()),
            'maintenance' => $maintenance instanceof MaintenanceState ? [
                'since' => $maintenance->since->format(DateTimeInterface::ATOM),
                'retry_after' => $maintenance->retryAfterSeconds,
            ] : null,
        ], $report->status()->isAvailable() ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(array $data, int $status): JsonResponse
    {
        return new JsonResponse($data, $status, ['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex']);
    }
}
