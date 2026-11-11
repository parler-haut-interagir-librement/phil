<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Infrastructure;

use Phil\MonitoringBundle\Application\HealthChecker;
use Phil\MonitoringBundle\Application\Heartbeats;
use Phil\MonitoringBundle\Application\Maintenance;
use Phil\MonitoringBundle\Application\Monitoring;
use Phil\MonitoringBundle\Domain\ValueObject\CheckOutcome;
use Phil\MonitoringBundle\Domain\ValueObject\ExpectedTask;
use Phil\MonitoringBundle\Domain\ValueObject\MaintenanceState;
use Phil\MonitoringBundle\Infrastructure\Http\HealthController;
use Phil\MonitoringBundle\Tests\Unit\Support\InMemoryHeartbeats;
use Phil\MonitoringBundle\Tests\Unit\Support\InMemoryMaintenance;
use Phil\MonitoringBundle\Tests\Unit\Support\StubCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[CoversClass(HealthController::class)]
final class HealthControllerTest extends TestCase
{
    private MockClock $clock;

    private InMemoryHeartbeats $beats;

    private InMemoryMaintenance $maintenance;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-08 10:00:00 UTC');
        $this->beats = new InMemoryHeartbeats();
        $this->maintenance = new InMemoryMaintenance();
    }

    public function testLivenessRunsNoCheck(): void
    {
        $response = $this->controller(CheckOutcome::down('gone'))->live();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['status' => 'up'], $this->json($response));
        $this->assertNotCached($response);
    }

    public function testReadinessGivesTheOverallStatusOnly(): void
    {
        $up = $this->controller(CheckOutcome::degraded('3 messages waiting'))->ready();
        self::assertSame(200, $up->getStatusCode());
        self::assertSame(['status' => 'degraded'], $this->json($up));
        $this->assertNotCached($up);

        $down = $this->controller(CheckOutcome::down('gone'))->ready();
        self::assertSame(503, $down->getStatusCode());
        self::assertSame(['status' => 'down'], $this->json($down));
    }

    public function testTheDetailsAreNotFoundWithoutAToken(): void
    {
        foreach ([null, ''] as $token) {
            $refusal = null;

            try {
                $this->controller(CheckOutcome::up(), $token)->details(new Request());
            } catch (NotFoundHttpException $exception) {
                $refusal = $exception;
            }

            self::assertInstanceOf(NotFoundHttpException::class, $refusal);
        }
    }

    public function testTheDetailsNeedTheBearerToken(): void
    {
        $controller = $this->controller(CheckOutcome::up(), 's3cret');

        foreach (['', 'Bearer wrong', 's3cret', 'Bearer s3cret '] as $authorization) {
            $request = new Request();
            $request->headers->set('Authorization', $authorization);
            $response = $controller->details($request);

            self::assertSame(401, $response->getStatusCode());
            self::assertSame('Bearer', $response->headers->get('WWW-Authenticate'));
            self::assertSame(['status' => 'unauthorized'], $this->json($response));
            $this->assertNotCached($response);
        }

        self::assertSame(401, $controller->details(new Request())->getStatusCode());
    }

    public function testTheDetailsOfEveryCheckTaskAndTheMaintenance(): void
    {
        $this->beats->beat('backup', $this->clock->now());
        $this->maintenance->enable(new MaintenanceState($this->clock->now(), 120));
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer s3cret');

        $response = $this->controller(CheckOutcome::degraded('slow'), 's3cret')->details($request);

        self::assertSame(200, $response->getStatusCode());
        $this->assertNotCached($response);
        self::assertSame([
            'status' => 'degraded',
            'checked_at' => '2026-11-08T10:00:00+00:00',
            'checks' => [['name' => 'database', 'status' => 'degraded', 'summary' => 'slow', 'duration_ms' => 0]],
            'heartbeats' => [
                ['task' => 'backup', 'state' => 'on_time', 'last_beat_at' => '2026-11-08T10:00:00+00:00', 'due_by' => '2026-11-08T11:00:00+00:00'],
                ['task' => 'report', 'state' => 'never', 'last_beat_at' => null, 'due_by' => null],
            ],
            'maintenance' => ['since' => '2026-11-08T10:00:00+00:00', 'retry_after' => 120],
        ], $this->json($response));
    }

    public function testTheDetailsOfADownSiteOpenAre503(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer s3cret');

        $response = $this->controller(CheckOutcome::down('gone'), 's3cret')->details($request);

        self::assertSame(503, $response->getStatusCode());
        self::assertNull($this->json($response)['maintenance']);
    }

    private function controller(CheckOutcome $outcome, ?string $token = null): HealthController
    {
        return new HealthController(new Monitoring(
            new HealthChecker([StubCheck::answering('database', $outcome)], $this->clock),
            new Heartbeats([new ExpectedTask('backup', 3600), new ExpectedTask('report', 60)], $this->beats, $this->clock),
            new Maintenance($this->maintenance, $this->clock, 300),
        ), $token);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function json(JsonResponse $response): array
    {
        $data = json_decode((string) $response->getContent(), true, 8, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    private function assertNotCached(JsonResponse $response): void
    {
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertSame('noindex', $response->headers->get('X-Robots-Tag'));
    }
}
