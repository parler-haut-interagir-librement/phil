<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\GuestRequest;

use Override;
use Phil\PrivacyBundle\Application\Erasure\ErasureRequests;
use Phil\PrivacyBundle\Application\Export\ExportPackage;
use Phil\PrivacyBundle\Application\Export\PlainExportReadme;
use Phil\PrivacyBundle\Application\GuestRequest\EmailGuestSubjects;
use Phil\PrivacyBundle\Application\GuestRequest\GuestRequests;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Domain\Erasure\RequestState;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequestKind;
use Phil\PrivacyBundle\Infrastructure\Http\GuestRequestController;
use Phil\PrivacyBundle\Infrastructure\Http\PrivacyPages;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\ErasureWorkbench;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\FixedExporter;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\MemoryGuestRequests;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\MessageLogger;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingLinkSender;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingLockStore;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\SubjectRecordingEraser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(GuestRequests::class)]
#[CoversClass(GuestRequestController::class)]
final class GuestErasureWorkflowTest extends TestCase
{
    private ErasureWorkbench $bench;

    private MemoryGuestRequests $store;

    private RecordingLinkSender $sender;

    private SubjectRecordingEraser $eraser;

    private MessageLogger $logger;

    #[Override]
    protected function setUp(): void
    {
        $this->bench = new ErasureWorkbench();
        $this->store = new MemoryGuestRequests();
        $this->sender = new RecordingLinkSender();
        $this->eraser = new SubjectRecordingEraser();
        $this->logger = new MessageLogger();
    }

    public function testAnOpenedLinkHandsTheRequestToTheWorkflowAndOpensNoMore(): void
    {
        $workflow = $this->workflow();
        $guests = $this->guests($workflow);
        $guests->submit(GuestRequestKind::Erasure, 'ada@example.org', '203.0.113.1');
        [$guest, $token] = $this->sender->sent[0];

        self::assertTrue($guests->erasesAsAWorkflow());
        $started = $guests->startErasure($guest->id, $token);

        self::assertNotNull($started);
        [$erasure, $secret] = $started;
        self::assertSame($guest->id, $erasure->guestRequestId);
        self::assertTrue($erasure->isOpenedBy($secret));
        self::assertSame([], $this->eraser->erased, 'Nothing is erased before the worker runs.');
        self::assertNull($guests->open($guest->id, $token), 'The link opens the request no more.');
        self::assertNull($guests->startErasure($guest->id, $token));
        self::assertEquals($this->bench->clock->now()->modify('+90 days'), $this->store->find($guest->id)?->expiresAt);
        self::assertSame('ada@example.org', $this->store->find($guest->id)?->email);
        self::assertContains('Request without an account {id} handed to the erasure request {erasure}.', $this->logger->messages);
        self::assertContains(['id' => $guest->id, 'erasure' => $erasure->id], $this->logger->contexts);

        $workflow->run($erasure->id, 'newsletter');

        self::assertSame(['ada@example.org'], $this->eraser->erased);
        self::assertSame(RequestState::Completed, $workflow->find($erasure->id)?->state);
        self::assertNull($this->store->find($guest->id), 'The request and its address are gone once the erasure is closed.');
    }

    public function testTheLockIsRenewedAfterTheErasureIsOpened(): void
    {
        $locks = new RecordingLockStore();
        $guests = $this->guests($this->workflow(), $locks);
        $guests->submit(GuestRequestKind::Erasure, 'ada@example.org', '203.0.113.1');
        [$guest, $token] = $this->sender->sent[0];
        $before = \count($locks->lifetimes);

        self::assertNotNull($guests->startErasure($guest->id, $token));

        self::assertSame(2, \count($locks->lifetimes) - $before);
    }

    public function testAnAccessLinkStartsNoErasure(): void
    {
        $guests = $this->guests($this->workflow());
        $guests->submit(GuestRequestKind::Access, 'ada@example.org', '203.0.113.1');
        [$guest, $token] = $this->sender->sent[0];

        self::assertNull($guests->startErasure($guest->id, $token));
        self::assertNotNull($guests->open($guest->id, $token));
        self::assertSame([], $this->bench->pending());
    }

    public function testWithoutAWorkflowNothingStarts(): void
    {
        $guests = $this->guests(null);
        $guests->submit(GuestRequestKind::Erasure, 'ada@example.org', '203.0.113.1');
        [$guest, $token] = $this->sender->sent[0];

        self::assertFalse($guests->erasesAsAWorkflow());
        self::assertNull($guests->startErasure($guest->id, $token));
        self::assertNotNull($guests->open($guest->id, $token));
    }

    public function testTheConfirmationPageGivesTheProgressLink(): void
    {
        $guests = $this->guests($this->workflow());
        $controller = new GuestRequestController($this->pages(), $guests);
        $guests->submit(GuestRequestKind::Erasure, 'ada@example.org', '203.0.113.1');
        [$guest, $token] = $this->sender->sent[0];

        $response = $controller->confirm(Request::create('/request/'.$guest->id, 'POST', ['_token' => 'tok', 'token' => $token, 'kind' => 'erasure']), $guest->id);

        self::assertMatchesRegularExpression('/^guest\/erasure_started\|[0-9a-f]{32}\|[A-Za-z0-9_-]{43}$/D', (string) $response->getContent());
        self::assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        self::assertSame([], $this->eraser->erased);

        $again = $controller->confirm(Request::create('/request/'.$guest->id, 'POST', ['_token' => 'tok', 'token' => $token, 'kind' => 'erasure']), $guest->id);
        self::assertSame('guest/invalid|', $again->getContent());
        self::assertSame(404, $again->getStatusCode());
    }

    private function workflow(): ErasureRequests
    {
        return new ErasureRequests($this->bench->store, $this->bench->outbox, [$this->eraser], [], $this->bench->clock, $this->store, new EmailGuestSubjects());
    }

    private function guests(?ErasureRequests $workflow, ?PersistingStoreInterface $locks = null): GuestRequests
    {
        $limits = new InMemoryStorage();

        return new GuestRequests(
            $this->store,
            $this->sender,
            new EmailGuestSubjects(),
            new SubjectExport([new FixedExporter('newsletter', ['list' => 'news'])]),
            new SubjectErasure([$this->eraser]),
            new ExportPackage(new PlainExportReadme(), $this->bench->clock),
            new LockFactory($locks ?? new InMemoryStore()),
            $this->bench->clock,
            new RateLimiterFactory(['id' => 'client', 'policy' => 'sliding_window', 'limit' => 5, 'interval' => '1 hour'], $limits),
            new RateLimiterFactory(['id' => 'address', 'policy' => 'sliding_window', 'limit' => 3, 'interval' => '1 day'], $limits),
            'secret',
            'PT1H',
            $this->logger,
            $workflow,
        );
    }

    private function pages(): PrivacyPages
    {
        $tokens = self::createStub(CsrfTokenManagerInterface::class);
        $tokens->method('getToken')->willReturn(new CsrfToken('phil_privacy', 'tok'));
        $tokens->method('isTokenValid')->willReturnCallback(static fn (CsrfToken $token): bool => 'tok' === $token->getValue());
        $twig = new Environment(new ArrayLoader([
            '@PhilPrivacy/guest/erasure_started.html.twig' => 'guest/erasure_started|{{ erasure_id }}|{{ token }}',
            '@PhilPrivacy/guest/invalid.html.twig' => 'guest/invalid|',
        ]), ['strict_variables' => true]);

        return new PrivacyPages($twig, $tokens, self::createStub(UrlGeneratorInterface::class), 'layout');
    }
}
