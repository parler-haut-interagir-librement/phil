<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Handler;

use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Override;
use Phil\PrivacyBundle\Application\AccountDeletion\AccountDeletions;
use Phil\PrivacyBundle\Application\AccountDeletion\DeletionWorkflow;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Exception\AccountErasureIncomplete;
use Phil\PrivacyBundle\Application\Exception\ErasureIncomplete;
use Phil\PrivacyBundle\Application\Handler\AdvanceAccountDeletionHandler;
use Phil\PrivacyBundle\Application\Message\AdvanceAccountDeletion;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Domain\AccountDeletion\DeletionPlace;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\MemoryAccountDeletions;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\MemoryExportArchive;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EventListener\AddErrorDetailsStampListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Worker;

/**
 * An eraser's exception can quote the person's data. Messenger keeps the message of what a handler throws, and of
 * every previous exception, in the envelope it sends to the retry and failure transports, and in its logs.
 */
#[CoversClass(AdvanceAccountDeletionHandler::class)]
#[CoversClass(AccountErasureIncomplete::class)]
#[CoversClass(ErasureIncomplete::class)]
final class FailedErasureInMessengerTest extends TestCase
{
    public const string LEAK = "Duplicate entry 'jane.doe@example.org' for key 'members_email'";

    public function testTheEraserMessageReachesNeitherTheTransportsNorTheLogs(): void
    {
        $log = new TestHandler();
        $logger = new Logger('test', [$log]);
        $clock = new MockClock('2026-01-01T00:00:00Z');
        $store = new MemoryAccountDeletions();
        $deletions = new AccountDeletions($store, DeletionWorkflow::create(), new LockFactory(new InMemoryStore()), new SubjectExport([]), new SubjectErasure([$this->leakingEraser()]), new MemoryExportArchive(), $clock, 'P30D', 'P7D', logger: $logger);
        $request = $deletions->request(DataSubject::fromString('member-1'));
        $clock->modify('+30 days');

        $async = new InMemoryTransport(new PhpSerializer());
        $failed = new InMemoryTransport(new PhpSerializer());
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([AdvanceAccountDeletion::class => [new AdvanceAccountDeletionHandler($deletions)]]))]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new AddErrorDetailsStampListener());
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener(
            new ServiceLocator(['async' => static fn (): InMemoryTransport => $async]),
            new ServiceLocator(['async' => static fn (): MultiplierRetryStrategy => new MultiplierRetryStrategy(1, 0, 1, 0, 0)]),
            $logger,
        ));
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(['async' => static fn (): InMemoryTransport => $failed]), $logger));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(2));

        $async->send(new Envelope(new AdvanceAccountDeletion($request->id)));
        new Worker(['async' => $async], $bus, $dispatcher, $logger)->run(['sleep' => 1]);

        self::assertSame(DeletionPlace::Exported, $store->rows[$request->id]->getPlace());
        self::assertCount(1, $failed->getSent());

        $everything = serialize([$async->getSent(), $async->getRejected(), $failed->getSent()]);
        self::assertStringNotContainsString('jane.doe@example.org', $everything, "The eraser's message reached a transport.");
        self::assertStringNotContainsString('member-1', $everything, 'The person reached a transport.');

        self::assertNotSame([], $log->getRecords());

        // What a log file receives: the formatters write the message of an exception and of every previous one.
        $line = new LineFormatter(null, null, true, false, true);
        $json = new JsonFormatter(includeStacktraces: true);

        foreach ($log->getRecords() as $record) {
            $text = $line->format($record).$json->format($record);
            self::assertStringNotContainsString('jane.doe@example.org', $text);
            self::assertStringNotContainsString('member-1', $text);
        }

        $envelope = $failed->getSent()[0];
        self::assertInstanceOf(SentToFailureTransportStamp::class, $envelope->last(SentToFailureTransportStamp::class));
        $details = $envelope->last(ErrorDetailsStamp::class);
        self::assertInstanceOf(ErrorDetailsStamp::class, $details);
        self::assertSame(AccountErasureIncomplete::class, $details->getExceptionClass());
        self::assertSame(\sprintf('Account deletion %s: the erasure failed in module(s) members; it is retried.', $request->id), $details->getExceptionMessage());
        self::assertSame([], $details->getFlattenException()?->getAllPrevious());

        self::assertTrue($log->hasWarningThatContains('Error thrown while handling message'));
        self::assertTrue($log->hasCriticalThatContains('Removing from transport after {retryCount} retries'));
        self::assertTrue($log->hasInfoThatContains('will be sent to the failure transport {transport}'));
    }

    public function testTheHandlerNamesTheRequestAndTheModulesOnly(): void
    {
        $clock = new MockClock('2026-01-01T00:00:00Z');
        $deletions = new AccountDeletions(new MemoryAccountDeletions(), DeletionWorkflow::create(), new LockFactory(new InMemoryStore()), new SubjectExport([]), new SubjectErasure([$this->leakingEraser(), $this->leakingEraser('billing')]), new MemoryExportArchive(), $clock, 'P30D', 'P7D');
        $request = $deletions->request(DataSubject::fromString('member-1'));
        $clock->modify('+30 days');

        try {
            new AdvanceAccountDeletionHandler($deletions)(new AdvanceAccountDeletion($request->id));
            self::fail('A failed erasure must reach Messenger, which retries it.');
        } catch (AccountErasureIncomplete $accountErasureIncomplete) {
            self::assertSame($request->id, $accountErasureIncomplete->deletionId);
            self::assertSame(['billing', 'members'], $accountErasureIncomplete->modules);
            self::assertSame(\sprintf('Account deletion %s: the erasure failed in module(s) billing, members; it is retried.', $request->id), $accountErasureIncomplete->getMessage());
            self::assertNull($accountErasureIncomplete->getPrevious());
            self::assertSame(0, $accountErasureIncomplete->getCode());
        }
    }

    public function testAModuleNamedWithDigitsOnlyStaysAString(): void
    {
        $erasure = ErasureIncomplete::after([], self::failureOf('7'));

        self::assertSame(['7'], AccountErasureIncomplete::of('d-1', $erasure)->modules);
    }

    /**
     * @param non-empty-string $module
     *
     * @return non-empty-array<string, RuntimeException>
     */
    private static function failureOf(string $module): array
    {
        return [$module => new RuntimeException('x')];
    }

    private function leakingEraser(string $module = 'members'): PersonalDataEraser
    {
        return new readonly class($module) implements PersonalDataEraser {
            public function __construct(
                private string $module,
            ) {
            }

            #[Override]
            public function module(): ModuleName
            {
                return ModuleName::fromString($this->module);
            }

            #[Override]
            public function erase(DataSubject $subject): ErasureReport
            {
                throw new RuntimeException(FailedErasureInMessengerTest::LEAK);
            }
        };
    }
}
