<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Tests\Unit;

use Phil\Checkin\AdmissionPolicy;
use Phil\Checkin\Attendance;
use Phil\Checkin\CheckIn;
use Phil\Checkin\Contract\CheckInLedger;
use Phil\Checkin\InMemoryCheckInLedger;
use Phil\Checkin\Offline\OfflineSync;
use Phil\Checkin\ScanId;
use Phil\CheckinBundle\Application\Event\CheckInRecorded;
use Phil\CheckinBundle\Infrastructure\Http\CheckinController;
use Phil\CheckinBundle\Infrastructure\Http\CheckinRequest;
use Phil\CheckinBundle\Infrastructure\Rules\CheckpointDesks;
use Phil\CheckinBundle\Infrastructure\Rules\CheckpointRules;
use Phil\CheckinBundle\Infrastructure\Rules\ConfiguredAdmissionRules;
use Phil\CheckinBundle\Tests\Unit\Fixture\CheckpointGrants;
use Phil\CheckinBundle\Tests\Unit\Fixture\FixedCredentials;
use Phil\CheckinBundle\Tests\Unit\Fixture\FixedTokens;
use Phil\CheckinBundle\Tests\Unit\Fixture\RecordingEvents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(CheckinController::class)]
final class CheckinControllerTest extends TestCase
{
    private InMemoryCheckInLedger $ledger;

    private MockClock $clock;

    private RecordingEvents $events;

    protected function setUp(): void
    {
        $this->ledger = new InMemoryCheckInLedger();
        $this->clock = new MockClock('2026-11-08 10:00:00 UTC');
        $this->events = new RecordingEvents();
    }

    public function testAScanIsDecidedRecordedAndAnswered(): void
    {
        $response = $this->controller()->scan($this->post(['code' => 'CODE-1', 'checkpoint' => 'hall', 'device' => 'door-1', 'id' => 's1']));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame([
            'outcome' => 'admitted',
            'replayed' => false,
            'id' => 's1',
            'credential' => ['id' => 'T-1', 'label' => 'Élise'],
            'attendance' => ['entries' => 1, 'inside' => true, 'lastEntryAt' => $this->clock->now()->getTimestamp()],
        ], $this->json($response));
        self::assertSame('door-1', $this->ledger->all()[0]->device->value);
    }

    public function testAScanGoesInByDefaultAndOutWhenAsked(): void
    {
        $controller = $this->controller();
        $controller->scan($this->post(['code' => 'CODE-2', 'checkpoint' => 'hall', 'device' => 'door-1']));

        $out = $this->json($controller->scan($this->post(['code' => 'CODE-2', 'checkpoint' => 'hall', 'device' => 'door-1', 'direction' => 'out'])));

        self::assertSame('checked_out', $out['outcome']);
        self::assertSame(['entries' => 1, 'inside' => false, 'lastEntryAt' => $this->clock->now()->getTimestamp()], $out['attendance']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', \is_string($out['id']) ? $out['id'] : '');
    }

    public function testAnUnknownCodeIsAnsweredWithoutCredential(): void
    {
        self::assertSame(
            ['outcome' => 'unknown', 'replayed' => false, 'id' => null, 'credential' => null, 'attendance' => ['entries' => 0, 'inside' => false, 'lastEntryAt' => null]],
            $this->json($this->controller()->scan($this->post(['code' => 'NOPE', 'checkpoint' => 'hall', 'device' => 'door-1']))),
        );
    }

    public function testAScanSentAgainIsReplayed(): void
    {
        $controller = $this->controller();
        $controller->scan($this->post(['code' => 'CODE-1', 'checkpoint' => 'hall', 'device' => 'door-1', 'id' => 's1']));

        self::assertTrue($this->json($controller->scan($this->post(['code' => 'CODE-1', 'checkpoint' => 'hall', 'device' => 'door-1', 'id' => 's1'])))['replayed']);
    }

    public function testTheEndpointsAnswerNothingUntilTurnedOn(): void
    {
        $controller = new CheckinController($this->desks(), new CheckinRequest(new FixedTokens()), new CheckpointGrants(), $this->sync());

        foreach ([$controller->scan($this->post([])), $controller->manifest($this->get([])), $controller->sync($this->post([]))] as $response) {
            self::assertSame(404, $response->getStatusCode());
            self::assertSame(['error' => 'disabled'], $this->json($response));
        }
    }

    public function testTheOfflineEndpointsAnswerNothingWithoutACatalog(): void
    {
        $controller = new CheckinController($this->desks(catalog: false), new CheckinRequest(new FixedTokens()), new CheckpointGrants(), enabled: true);

        self::assertSame(404, $controller->manifest($this->get(['checkpoint' => 'hall', 'device' => 'd1']))->getStatusCode());
        self::assertSame(['error' => 'disabled'], $this->json($controller->sync($this->post(['checkpoint' => 'hall', 'device' => 'd1', 'scans' => []]))));
    }

    public function testARequestNotFromTheControllerIsForbidden(): void
    {
        $request = $this->post(['code' => 'CODE-1', 'checkpoint' => 'hall', 'device' => 'door-1']);
        $request->headers->remove('X-Phil-Checkin');

        $response = $this->controller()->scan($request);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(['error' => 'forbidden'], $this->json($response));
        self::assertSame([], $this->ledger->all());
    }

    public function testACheckpointTheSecurityDoesNotGrantIsForbidden(): void
    {
        $response = $this->controller()->scan($this->post(['code' => 'CODE-1', 'checkpoint' => 'stage', 'device' => 'door-1']));

        self::assertSame(403, $response->getStatusCode());
        self::assertSame([], $this->ledger->all());
    }

    public function testWithoutSecurityNothingIsGranted(): void
    {
        $controller = new CheckinController($this->desks(), new CheckinRequest(new FixedTokens()), enabled: true);

        self::assertSame(403, $controller->scan($this->post(['code' => 'CODE-1', 'checkpoint' => 'hall', 'device' => 'door-1']))->getStatusCode());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalid(): iterable
    {
        yield 'no code' => ['{"checkpoint": "hall", "device": "d1"}', 'A scan has a "code", and may have a "direction" and an "id", all texts.'];
        yield 'a direction not a text' => ['{"code": "CODE-1", "checkpoint": "hall", "device": "d1", "direction": 1}', 'A scan has a "code", and may have a "direction" and an "id", all texts.'];
        yield 'an id not a text' => ['{"code": "CODE-1", "checkpoint": "hall", "device": "d1", "id": 7}', 'A scan has a "code", and may have a "direction" and an "id", all texts.'];
        yield 'a bad direction' => ['{"code": "CODE-1", "checkpoint": "hall", "device": "d1", "direction": "up"}', 'A direction is "in" or "out".'];
        yield 'no checkpoint' => ['{"code": "CODE-1", "device": "d1"}', 'A checkpoint identifier is 1 to 64 letters, digits, ".", "_", ":" or "-".'];
        yield 'no device' => ['{"code": "CODE-1", "checkpoint": "hall"}', 'A device identifier is 1 to 64 letters, digits, ".", "_", ":" or "-".'];
        yield 'a bad code' => ['{"code": "", "checkpoint": "hall", "device": "d1"}', 'A credential code is valid UTF-8 without control characters, 1 to 512 bytes.'];
        yield 'not JSON' => ['code=CODE-1', 'The body is a JSON object.'];
    }

    #[DataProvider('invalid')]
    public function testAnInvalidScanIsABadRequest(string $body, string $reason): void
    {
        $response = $this->controller()->scan($this->post($body));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['error' => 'invalid', 'reason' => $reason], $this->json($response));
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    public function testTheManifestIsAnsweredForTheCheckpointAndTheDevice(): void
    {
        $response = $this->controller()->manifest($this->get(['checkpoint' => 'hall', 'device' => 'door-1']));
        $manifest = $this->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame('hall', $manifest['checkpoint']);
        self::assertSame('door-1', $manifest['device']);
        self::assertCount(2, (array) $manifest['credentials']);
    }

    public function testAManifestOverItsLimitIsTooLarge(): void
    {
        $controller = new CheckinController($this->desks(limit: 1), new CheckinRequest(new FixedTokens()), new CheckpointGrants(), $this->sync(), enabled: true);
        $response = $controller->manifest($this->get(['checkpoint' => 'hall', 'device' => 'door-1']));

        self::assertSame(413, $response->getStatusCode());
        self::assertSame(['error' => 'too_large'], $this->json($response));
    }

    public function testAManifestOfWeakCodesIsAConflict(): void
    {
        $controller = new CheckinController($this->desks(minCodeBits: 64), new CheckinRequest(new FixedTokens()), new CheckpointGrants(), $this->sync(), enabled: true);
        $response = $controller->manifest($this->get(['checkpoint' => 'hall', 'device' => 'door-1']));

        self::assertSame(409, $response->getStatusCode());
        self::assertSame(['error' => 'weak_codes'], $this->json($response));
    }

    public function testAManifestIsForbiddenOnACheckpointNotGranted(): void
    {
        self::assertSame(403, $this->controller()->manifest($this->get(['checkpoint' => 'stage', 'device' => 'door-1']))->getStatusCode());
    }

    public function testAJournalIsSynchronised(): void
    {
        $scan = ['id' => 's1', 'credential' => 'T-1', 'direction' => 'in', 'outcome' => 'admitted', 'scannedAt' => $this->clock->now()->getTimestamp() - 60];
        $response = $this->controller()->sync($this->post(['checkpoint' => 'hall', 'device' => 'door-1', 'scans' => [$scan]]));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['scans' => [['id' => 's1', 'status' => 'recorded']]], $this->json($response));
        self::assertTrue($this->ledger->all()[0]->offline);
        self::assertSame('door-1', $this->ledger->all()[0]->device->value);
    }

    public function testAJournalOverTheBatchLimitIsABadRequest(): void
    {
        $scan = ['id' => 's1', 'credential' => 'T-1', 'direction' => 'in', 'outcome' => 'admitted', 'scannedAt' => 1];
        $controller = new CheckinController($this->desks(), new CheckinRequest(new FixedTokens()), new CheckpointGrants(), $this->sync(), 1, true);
        $response = $controller->sync($this->post(['checkpoint' => 'hall', 'device' => 'door-1', 'scans' => [$scan, $scan]]));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['error' => 'invalid', 'reason' => 'A batch holds at most 1 scans.'], $this->json($response));
    }

    public function testAMissingJournalIsABadRequest(): void
    {
        self::assertSame(['error' => 'invalid', 'reason' => 'A batch is a list of scans.'], $this->json($this->controller()->sync($this->post(['checkpoint' => 'hall', 'device' => 'door-1']))));
    }

    public function testTheDefaultBatchLimitIsFiveHundred(): void
    {
        $scan = ['id' => 's1', 'credential' => 'T-1', 'direction' => 'in', 'outcome' => 'admitted', 'scannedAt' => 1];
        $response = $this->controller()->sync($this->post(['checkpoint' => 'hall', 'device' => 'door-1', 'scans' => array_fill(0, 501, $scan)]));

        self::assertSame(['error' => 'invalid', 'reason' => 'A batch holds at most 500 scans.'], $this->json($response));
    }

    public function testALedgerUnderContentionIsBusy(): void
    {
        $busy = new class implements CheckInLedger {
            public function attendance(string $credentialId): Attendance
            {
                return Attendance::none();
            }

            public function attendances(array $credentialIds): array
            {
                return [];
            }

            /**
             * @phpstan-pure
             */
            public function find(ScanId $id): ?CheckIn
            {
                return null;
            }

            public function append(CheckIn $checkIn): bool
            {
                return false;
            }
        };
        $controller = new CheckinController($this->desks(ledger: $busy), new CheckinRequest(new FixedTokens()), new CheckpointGrants(), enabled: true);
        $response = $controller->scan($this->post(['code' => 'CODE-1', 'checkpoint' => 'hall', 'device' => 'door-1']));

        self::assertSame(503, $response->getStatusCode());
        self::assertSame(['error' => 'busy'], $this->json($response));
    }

    public function testARecordedScanIsDispatchedOnceAndAReplayOrAnUnknownCodeIsNot(): void
    {
        $controller = $this->controller();
        $controller->scan($this->post(['code' => 'CODE-1', 'checkpoint' => 'hall', 'device' => 'door-1', 'id' => 's1']));
        $controller->scan($this->post(['code' => 'CODE-1', 'checkpoint' => 'hall', 'device' => 'door-1', 'id' => 's1']));
        $controller->scan($this->post(['code' => 'CODE-1', 'checkpoint' => 'hall', 'device' => 'door-2', 'id' => 's2']));
        $controller->scan($this->post(['code' => 'NOPE', 'checkpoint' => 'hall', 'device' => 'door-1']));

        self::assertEquals([
            CheckInRecorded::online($this->ledger->all()[0]),
            CheckInRecorded::online($this->ledger->all()[1]),
        ], $this->events->dispatched);
        self::assertSame(['s1', 'T-1', 'hall', 'door-1', 'in', 'admitted', false, false], $this->fields($this->events->dispatched[0]));
        self::assertSame(['s2', 'T-1', 'hall', 'door-2', 'in', 'already_admitted', false, false], $this->fields($this->events->dispatched[1]));
    }

    public function testTheScansOfAJournalRecordedAreDispatchedAndTheOthersAreNot(): void
    {
        $at = $this->clock->now()->getTimestamp() - 60;
        $scans = [
            ['id' => 's1', 'credential' => 'T-1', 'direction' => 'in', 'outcome' => 'admitted', 'scannedAt' => $at],
            ['id' => 's2', 'credential' => 'T-1', 'direction' => 'in', 'outcome' => 'admitted', 'scannedAt' => $at + 1],
            ['id' => 's3', 'credential' => 'T-9', 'direction' => 'in', 'outcome' => 'admitted', 'scannedAt' => $at],
        ];
        $controller = $this->controller();
        $controller->sync($this->post(['checkpoint' => 'hall', 'device' => 'door-1', 'scans' => $scans]));
        $controller->sync($this->post(['checkpoint' => 'hall', 'device' => 'door-1', 'scans' => $scans]));

        self::assertCount(2, $this->events->dispatched);
        self::assertSame(['s1', 'T-1', 'hall', 'door-1', 'in', 'admitted', true, false], $this->fields($this->events->dispatched[0]));
        self::assertSame(['s2', 'T-1', 'hall', 'door-1', 'in', 'admitted', true, true], $this->fields($this->events->dispatched[1]));
    }

    public function testAJournalIsRecordedWithoutADispatcher(): void
    {
        $controller = new CheckinController($this->desks(), new CheckinRequest(new FixedTokens()), new CheckpointGrants(), $this->sync(), enabled: true);
        $scan = ['id' => 's1', 'credential' => 'T-1', 'direction' => 'in', 'outcome' => 'admitted', 'scannedAt' => $this->clock->now()->getTimestamp() - 60];

        self::assertSame(['scans' => [['id' => 's1', 'status' => 'recorded']]], $this->json($controller->sync($this->post(['checkpoint' => 'hall', 'device' => 'door-1', 'scans' => [$scan]]))));
    }

    public function testTheRuleOfACheckpointRefusesAnEntryButNeverAnExit(): void
    {
        $rules = ['hall' => 'credential.label != null'];
        $controller = new CheckinController($this->desks(rules: $rules), new CheckinRequest(new FixedTokens()), new CheckpointGrants(), $this->sync(), enabled: true);

        self::assertSame('admitted', $this->json($controller->scan($this->post(['code' => 'CODE-1', 'checkpoint' => 'hall', 'device' => 'door-1'])))['outcome']);
        $refused = $this->json($controller->scan($this->post(['code' => 'CODE-2', 'checkpoint' => 'hall', 'device' => 'door-1'])));
        self::assertSame('wrong_checkpoint', $refused['outcome']);
        self::assertSame(['id' => 'T-2', 'label' => null], $refused['credential']);
        self::assertSame('checked_out', $this->json($controller->scan($this->post(['code' => 'CODE-1', 'checkpoint' => 'hall', 'device' => 'door-1', 'direction' => 'out'])))['outcome']);
        self::assertSame(['admitted', 'wrong_checkpoint', 'checked_out'], array_map(static fn (CheckIn $checkIn): string => $checkIn->outcome->value, $this->ledger->all()));
    }

    public function testTheManifestOfACheckpointCarriesOnlyTheCredentialsItsRuleAdmits(): void
    {
        $controller = new CheckinController($this->desks(rules: ['hall' => 'credential.label != null']), new CheckinRequest(new FixedTokens()), new CheckpointGrants(), $this->sync(), enabled: true);

        $manifest = $this->json($controller->manifest($this->get(['checkpoint' => 'hall', 'device' => 'door-1'])));

        self::assertSame(['T-1'], array_map(static fn (mixed $entry): mixed => \is_array($entry) ? $entry[1] : null, (array) $manifest['credentials']));
    }

    /**
     * @return list<mixed>
     */
    private function fields(object $event): array
    {
        self::assertInstanceOf(CheckInRecorded::class, $event);

        return [$event->id->value, $event->credentialId, $event->checkpoint->value, $event->device->value, $event->direction->value, $event->outcome->value, $event->offline, $event->conflict];
    }

    private function controller(): CheckinController
    {
        return new CheckinController($this->desks(), new CheckinRequest(new FixedTokens()), new CheckpointGrants(), $this->sync(), enabled: true, events: $this->events);
    }

    /**
     * @param array<string, string> $rules
     */
    private function desks(bool $catalog = true, ?CheckInLedger $ledger = null, array $rules = [], int $limit = 10_000, int $minCodeBits = 0): CheckpointDesks
    {
        $checkpointRules = new CheckpointRules(new ConfiguredAdmissionRules($rules), $this->clock);

        return new CheckpointDesks(new FixedCredentials(), $ledger ?? $this->ledger, $this->clock, new AdmissionPolicy(), $checkpointRules, $catalog ? new FixedCredentials() : null, maxCredentials: $limit, minCodeBits: $minCodeBits);
    }

    private function sync(): OfflineSync
    {
        return new OfflineSync(new FixedCredentials(), $this->ledger, $this->clock);
    }

    /**
     * @param array<string, mixed>|string $body
     */
    private function post(array|string $body): Request
    {
        return Request::create('/_checkin/scan', 'POST', server: ['HTTP_X_PHIL_CHECKIN' => '1', 'HTTP_CSRF_TOKEN' => 'good'], content: \is_string($body) ? $body : json_encode($body, \JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, string> $query
     */
    private function get(array $query): Request
    {
        return Request::create('/_checkin/manifest', 'GET', $query, server: ['HTTP_X_PHIL_CHECKIN' => '1']);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function json(Response $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true, 16, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
