<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\Outbox\Exception\ForgottenRecord;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\Payload;
use Phil\Outbox\RecordId;
use Phil\PrivacyBundle\Application\Contract\CurrentDataSubject;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Erasure\ErasureRequests;
use Phil\PrivacyBundle\Application\Exception\ModuleErasureFailed;
use Phil\PrivacyBundle\Application\Message\EraseModule;
use Phil\PrivacyBundle\Domain\Erasure\RequestState;
use Phil\PrivacyBundle\Domain\Erasure\StepState;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\PrivacyBundle\Infrastructure\Console\ErasureRequestCommand;
use Phil\PrivacyBundle\Infrastructure\Http\ErasureProgressController;
use Phil\PrivacyBundle\Infrastructure\Http\PrivacyPages;
use Phil\PrivacyBundle\Infrastructure\Messenger\ErasureFailureListener;
use Phil\PrivacyBundle\Infrastructure\Outbox\EraseModuleCodec;
use Phil\PrivacyBundle\Infrastructure\Outbox\EraseModuleRelay;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\ErasureWorkbench;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingEraser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(EraseModuleCodec::class)]
#[CoversClass(EraseModuleRelay::class)]
#[CoversClass(ErasureFailureListener::class)]
#[CoversClass(ErasureProgressController::class)]
#[CoversClass(ErasureRequestCommand::class)]
final class ErasureWorkflowPartsTest extends TestCase
{
    private ErasureWorkbench $bench;

    private ErasureRequests $requests;

    #[Override]
    protected function setUp(): void
    {
        $this->bench = new ErasureWorkbench();
        $this->requests = new ErasureRequests($this->bench->store, $this->bench->outbox, [new RecordingEraser('billing'), new RecordingEraser('forum', true)], [], $this->bench->clock);
    }

    public function testTheCodecRebuildsTheMessageOfARequestThatIsKept(): void
    {
        $request = $this->requests->open(DataSubject::fromString('member-7'));
        $codec = new EraseModuleCodec($this->bench->store);
        $record = $this->bench->pending()[0];

        self::assertSame([EraseModule::TYPE], $codec->types());
        self::assertEquals(new EraseModule($request->id, 'billing'), $codec->decode($record, $record->payload()));
    }

    public function testTheCodecSkipsTheRecordOfARequestThatIsGone(): void
    {
        $codec = new EraseModuleCodec($this->bench->store);

        foreach ([str_repeat('0', 32), 'not-an-id', str_repeat('a', 40)] as $request) {
            $payload = Payload::of(['request' => $request, 'module' => 'billing']);

            try {
                $codec->decode(OutboxRecord::pending(RecordId::generate(), EraseModule::TYPE, $payload, null, null, $this->bench->clock->now()), $payload);
                self::fail('The record of a request that is gone was rebuilt.');
            } catch (ForgottenRecord $forgottenRecord) {
                self::assertSame(\sprintf('The erasure request %s is gone.', mb_strimwidth($request, 0, 32, '…')), $forgottenRecord->getMessage());
            }
        }
    }

    public function testTheRelayHandsTheMessageToTheBus(): void
    {
        $message = new EraseModule(str_repeat('a', 32), 'billing');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with($message)->willReturn(new Envelope($message));

        new EraseModuleRelay($bus)($message);
    }

    public function testTheLastFailureOfAModuleNeedsAttentionAndAnEarlierOneWaits(): void
    {
        $request = $this->requests->open(DataSubject::fromString('member-7'));

        try {
            $this->requests->run($request->id, 'forum');
        } catch (ModuleErasureFailed) {
        }

        $listener = new ErasureFailureListener($this->requests);
        $envelope = new Envelope(new EraseModule($request->id, 'forum'));
        $retried = new WorkerMessageFailedEvent($envelope, 'async', new RuntimeException('down'));
        $retried->setForRetry();

        $listener($retried);
        self::assertSame(StepState::Pending, $this->bench->store->step($request->id, 'forum')?->state);

        $listener(new WorkerMessageFailedEvent(new Envelope(new stdClass()), 'async', new RuntimeException('down')));
        self::assertSame(RequestState::Running, $this->requests->find($request->id)?->state);

        $listener(new WorkerMessageFailedEvent($envelope, 'async', new RuntimeException('down')));
        self::assertSame(StepState::NeedsAttention, $this->bench->store->step($request->id, 'forum')?->state);
        self::assertSame(RequestState::NeedsAttention, $this->requests->find($request->id)?->state);
    }

    public function testTheProgressIsShownToThePersonAsAPageOrAsJson(): void
    {
        $request = $this->requests->open(DataSubject::fromString('member-7'));
        $this->requests->run($request->id, 'billing');
        $controller = $this->controller(DataSubject::fromString('member-7'));

        $page = $controller->progress(Request::create('/'), $request->id);
        self::assertSame('erasure/progress|running|1/2|billing:completed,forum:pending,', $page->getContent());
        self::assertSame('no-store, private', $page->headers->get('Cache-Control'));
        self::assertSame('no-referrer', $page->headers->get('Referrer-Policy'));
        self::assertSame('noindex', $page->headers->get('X-Robots-Tag'));

        $json = $controller->progress(Request::create('/'), $request->id, 'json');
        self::assertSame('application/json', $json->headers->get('Content-Type'));
        self::assertSame('no-store, private', $json->headers->get('Cache-Control'));
        self::assertSame('no-referrer', $json->headers->get('Referrer-Policy'));
        self::assertSame('noindex', $json->headers->get('X-Robots-Tag'));
        $decoded = json_decode((string) $json->getContent(), true);
        self::assertIsArray($decoded);
        self::assertSame($request->id, $decoded['id']);
        self::assertSame(1, $decoded['completed']);
        self::assertStringNotContainsString('member-7', (string) $json->getContent());
    }

    public function testTheProgressIsShownToAnOperator(): void
    {
        $request = $this->requests->open(DataSubject::fromString('member-7'));
        $authorization = $this->createMock(AuthorizationCheckerInterface::class);
        $authorization->expects(self::once())->method('isGranted')->with('ROLE_ERASURE_DESK')->willReturn(true);

        self::assertSame(200, $this->controller(DataSubject::fromString('member-8'), $authorization)->progress(Request::create('/'), $request->id)->getStatusCode());
    }

    public function testTheProgressOfAGuestRequestIsShownToTheHolderOfItsSecret(): void
    {
        [$request, $token] = $this->requests->openForGuest(str_repeat('b', 32));

        self::assertSame(200, $this->controller(null)->progress(Request::create('/', 'GET', ['token' => $token]), $request->id)->getStatusCode());
        $this->assertNotFound($this->controller(null), Request::create('/', 'GET', ['token' => strrev($token)]), $request->id);
    }

    public function testAnyoneElseGetsTheSameAnswerAsForARequestThatDoesNotExist(): void
    {
        $request = $this->requests->open(DataSubject::fromString('member-7'));
        $denied = self::createStub(AuthorizationCheckerInterface::class);
        $denied->method('isGranted')->willReturn(false);

        $this->assertNotFound($this->controller(DataSubject::fromString('member-8'), $denied), Request::create('/'), $request->id);
        $this->assertNotFound($this->controller(null), Request::create('/'), $request->id);
        $this->assertNotFound($this->controller(DataSubject::fromString('member-7')), Request::create('/', 'GET', ['token' => str_repeat('a', 43)]), $request->id);
        $this->assertNotFound($this->controller(DataSubject::fromString('member-7')), Request::create('/'), str_repeat('0', 32));
        $this->assertNotFound(new ErasureProgressController($this->pages(), null, null, null, 'ROLE_X'), Request::create('/'), $request->id);

        $withoutSecurity = new ErasureProgressController($this->pages(), $this->requests, null, null, 'ROLE_X');
        $this->assertNotFound($withoutSecurity, Request::create('/'), $request->id);
    }

    public function testTheCommandShowsARequestModuleByModule(): void
    {
        $request = $this->requests->open(DataSubject::fromString('member-7'));
        $this->requests->run($request->id, 'billing');

        try {
            $this->requests->run($request->id, 'forum');
        } catch (ModuleErasureFailed) {
        }

        $tester = new CommandTester(new ErasureRequestCommand($this->requests));

        self::assertSame(Command::SUCCESS, $tester->execute(['id' => $request->id]));
        self::assertSame(implode("\n", [
            \sprintf('%s: running, 1 of 2 modules completed, inventory %s.', $request->id, $request->inventoryVersion),
            '- billing: completed, records deleted',
            '- forum: pending, 1 failed attempts',
        ])."\n", $tester->getDisplay());
    }

    public function testTheCommandReplaysAndClosesAsFailedOnlyWhenConfirmed(): void
    {
        $request = $this->requests->open(DataSubject::fromString('member-7'));
        $this->requests->giveUp($request->id, 'forum');
        $tester = new CommandTester(new ErasureRequestCommand($this->requests));

        self::assertSame(Command::SUCCESS, $tester->execute(['id' => $request->id, '--replay' => true]));
        self::assertStringStartsWith("Replayed: billing, forum.\n", $tester->getDisplay());

        self::assertSame(Command::INVALID, $tester->execute(['id' => $request->id, '--fail' => true]));
        self::assertSame("Nothing was closed: add --confirm to close the request as failed.\n", $tester->getDisplay());
        self::assertSame(RequestState::Running, $this->requests->find($request->id)?->state);

        self::assertSame(Command::SUCCESS, $tester->execute(['id' => $request->id, '--fail' => true, '--confirm' => true]));
        self::assertStringStartsWith("Closed as failed.\n".$request->id.': failed,', $tester->getDisplay());

        self::assertSame(Command::SUCCESS, $tester->execute(['id' => $request->id, '--fail' => true, '--confirm' => true]));
        self::assertStringStartsWith("The request is already closed.\n", $tester->getDisplay());

        self::assertSame(Command::SUCCESS, $tester->execute(['id' => $request->id, '--replay' => true]));
        self::assertStringStartsWith("Nothing to replay.\n", $tester->getDisplay());
    }

    public function testTheCommandShowsWhatAModuleLeftOfThePersonAndTheReason(): void
    {
        $requests = new ErasureRequests($this->bench->store, $this->bench->outbox, [new class implements PersonalDataEraser {
            #[Override]
            public function module(): ModuleName
            {
                return ModuleName::fromString('billing');
            }

            #[Override]
            public function erase(DataSubject $subject): ErasureReport
            {
                return ErasureReport::of('billing', ['invoices' => ErasureEffect::Survives, 'cards' => ErasureEffect::Anonymised]);
            }
        }, new RecordingEraser('forum')], [], $this->bench->clock);
        $request = $requests->open(DataSubject::fromString('member-7'));
        $requests->run($request->id, 'billing');
        $requests->giveUp($request->id, 'forum');

        $tester = new CommandTester(new ErasureRequestCommand($requests));

        $tester->execute(['id' => $request->id]);

        self::assertStringContainsString('- billing: completed, invoices survives (personal data left), cards anonymised', $tester->getDisplay());
        self::assertStringContainsString("- forum: needs_attention (retries_exhausted)\n", $tester->getDisplay());
    }

    public function testTheCommandFailsOnAnUnknownRequest(): void
    {
        $tester = new CommandTester(new ErasureRequestCommand($this->requests));

        self::assertSame(Command::FAILURE, $tester->execute(['id' => str_repeat('0', 32)]));
        self::assertSame("No erasure request has this identifier.\n", $tester->getDisplay());
    }

    private function assertNotFound(ErasureProgressController $controller, Request $request, string $id): void
    {
        try {
            $answered = $controller->progress($request, $id)->getStatusCode();
        } catch (NotFoundHttpException $notFoundHttpException) {
            $answered = $notFoundHttpException->getStatusCode();
        }

        self::assertSame(404, $answered, 'The progress was shown.');
    }

    private function controller(?DataSubject $subject, ?AuthorizationCheckerInterface $authorization = null): ErasureProgressController
    {
        $current = self::createStub(CurrentDataSubject::class);
        $current->method('current')->willReturn($subject);

        return new ErasureProgressController($this->pages(), $this->requests, $current, $authorization, 'ROLE_ERASURE_DESK');
    }

    private function pages(): PrivacyPages
    {
        $tokens = self::createStub(CsrfTokenManagerInterface::class);
        $tokens->method('getToken')->willReturn(new CsrfToken('phil_privacy', 'tok'));
        $twig = new Environment(new ArrayLoader([
            '@PhilPrivacy/erasure/progress.html.twig' => 'erasure/progress|{{ progress.request.state.value }}|{{ counts.completed }}/{{ counts.total }}|{% for step in progress.steps %}{{ step.module }}:{{ step.state.value }},{% endfor %}',
        ]), ['strict_variables' => true]);

        return new PrivacyPages($twig, $tokens, self::createStub(UrlGeneratorInterface::class), 'layout');
    }
}
