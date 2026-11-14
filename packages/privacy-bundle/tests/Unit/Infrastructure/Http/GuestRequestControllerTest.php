<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Http;

use Phil\PrivacyBundle\Application\Export\ExportPackage;
use Phil\PrivacyBundle\Application\Export\PlainExportReadme;
use Phil\PrivacyBundle\Application\GuestRequest\EmailGuestSubjects;
use Phil\PrivacyBundle\Application\GuestRequest\GuestRequests;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Infrastructure\Http\GuestRequestController;
use Phil\PrivacyBundle\Infrastructure\Http\PrivacyPages;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\FixedExporter;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\MemoryGuestRequests;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingLinkSender;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\SubjectRecordingEraser;
use Phil\PrivacyBundle\Tests\Unit\Fixture\Unzip;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(GuestRequestController::class)]
final class GuestRequestControllerTest extends TestCase
{
    private RecordingLinkSender $sender;

    private SubjectRecordingEraser $eraser;

    protected function setUp(): void
    {
        $this->sender = new RecordingLinkSender();
        $this->eraser = new SubjectRecordingEraser();
    }

    public function testTheFormAndTheConfirmationAreTheirTemplates(): void
    {
        $controller = $this->controller();

        self::assertSame('guest/request|', $controller->request(Request::create('/request'))->getContent());

        $controller->request($this->post('/request', ['kind' => 'erasure', 'email' => 'a@example.org']));
        [$request, $token] = $this->sender->sent[0];

        $confirm = $controller->confirm(Request::create('/request/'.$request->id, 'GET', ['token' => $token]), $request->id);
        self::assertSame('guest/confirm|'.$request->id.'|erasure|'.$token.'|no', $confirm->getContent());
        self::assertSame('no-referrer', $confirm->headers->get('Referrer-Policy'));
    }

    public function testAnErasureAModuleCouldNotCompleteAsksToTryAgain(): void
    {
        $controller = $this->controller();
        $controller->request($this->post('/request', ['kind' => 'erasure', 'email' => 'a@example.org']));
        [$request, $token] = $this->sender->sent[0];
        $this->eraser->fails = true;

        $response = $controller->confirm($this->post('/request/'.$request->id, ['token' => $token, 'kind' => 'erasure']), $request->id);

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertSame('guest/confirm|'.$request->id.'|erasure|'.$token.'|yes', $response->getContent());
        self::assertSame('no-referrer', $response->headers->get('Referrer-Policy'));

        $this->eraser->fails = false;
        $done = $controller->confirm($this->post('/request/'.$request->id, ['token' => $token, 'kind' => 'erasure']), $request->id);
        self::assertSame('guest/erased|', $done->getContent());
        self::assertSame(['a@example.org'], $this->eraser->erased);
    }

    public function testAClientOverItsLimitIsToldWhenToComeBack(): void
    {
        $controller = $this->controller(1);
        $controller->request($this->post('/request', ['kind' => 'access', 'email' => 'a@example.org']));

        $response = $controller->request($this->post('/request', ['kind' => 'access', 'email' => 'b@example.org']));

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        self::assertSame('guest/request|guest.error.too_many', $response->getContent());
        self::assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
    }

    public function testAnUnknownLinkIsNotFound(): void
    {
        $response = $this->controller()->confirm($this->post('/request/'.str_repeat('a', 32), ['token' => 'x', 'kind' => 'access']), str_repeat('a', 32));

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('guest/invalid|', $response->getContent());
        self::assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
    }

    public function testAForgedFormIsRefused(): void
    {
        $controller = $this->controller();
        $refused = 0;

        foreach ([
            static fn (): Response => $controller->request(Request::create('/request', 'POST', ['_token' => 'forged', 'kind' => 'access', 'email' => 'a@example.org'])),
            static fn (): Response => $controller->confirm(Request::create('/request/'.str_repeat('a', 32), 'POST', ['_token' => 'forged', 'token' => 'x', 'kind' => 'access']), str_repeat('a', 32)),
        ] as $page) {
            try {
                $page();
            } catch (BadRequestHttpException $badRequestHttpException) {
                self::assertSame('The CSRF token is not valid.', $badRequestHttpException->getMessage());
                ++$refused;
            }
        }

        self::assertSame(2, $refused);
        self::assertSame([], $this->sender->sent);
    }

    public function testAnUnknownKindOfRequestIsABadRequest(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageIsOrContains('The kind of request is "access" or "erasure".');

        $this->controller()->request($this->post('/request', ['kind' => 'rectification', 'email' => 'a@example.org']));
    }

    public function testAnAddressThatCannotReceiveMailIsShownAgainWithItsError(): void
    {
        $response = $this->controller()->request($this->post('/request', ['kind' => 'access', 'email' => 'not an address']));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('guest/request|guest.error.address', $response->getContent());
        self::assertSame([], $this->sender->sent);
    }

    public function testTheClientsAreToldApartByTheirAddress(): void
    {
        $controller = $this->controller(1);

        self::assertSame('guest/sent|', $controller->request($this->post('/request', ['kind' => 'access', 'email' => 'a@example.org'], '203.0.113.1'))->getContent());
        self::assertSame('guest/sent|', $controller->request($this->post('/request', ['kind' => 'access', 'email' => 'b@example.org'], '203.0.113.2'))->getContent());
        self::assertCount(2, $this->sender->sent);
    }

    public function testAnAccessLinkDownloadsTheArchive(): void
    {
        $controller = $this->controller();
        $controller->request($this->post('/request', ['kind' => 'access', 'email' => 'a@example.org']));
        [$request, $token] = $this->sender->sent[0];

        $response = $controller->confirm($this->post('/request/'.$request->id, ['token' => $token, 'kind' => 'access']), $request->id);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('application/zip', $response->headers->get('Content-Type'));
        self::assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        self::assertSame(['subject' => 'a@example.org', 'list' => 'news'], json_decode(Unzip::files((string) $response->getContent())['newsletter.json'], true));
    }

    public function testEveryPageIsNotFoundWhileTheRequestsAreOff(): void
    {
        $controller = new GuestRequestController($this->pages(), null);
        $refused = 0;

        foreach ([static fn (): Response => $controller->request(Request::create('/request')), static fn (): Response => $controller->confirm(Request::create('/request/x'), 'x')] as $page) {
            try {
                $page();
            } catch (NotFoundHttpException) {
                ++$refused;
            }
        }

        self::assertSame(2, $refused);
    }

    /**
     * @param array<string, string> $fields
     */
    private function post(string $path, array $fields, string $client = '203.0.113.1'): Request
    {
        return Request::create($path, 'POST', ['_token' => 'tok', ...$fields], server: ['REMOTE_ADDR' => $client]);
    }

    private function controller(int $perClient = 5): GuestRequestController
    {
        $clock = new MockClock('2026-11-10T12:00:00Z');
        $limits = new InMemoryStorage();

        return new GuestRequestController($this->pages(), new GuestRequests(
            new MemoryGuestRequests(),
            $this->sender,
            new EmailGuestSubjects(),
            new SubjectExport([new FixedExporter('newsletter', ['list' => 'news'])]),
            new SubjectErasure([$this->eraser]),
            new ExportPackage(new PlainExportReadme(), $clock),
            new LockFactory(new InMemoryStore()),
            $clock,
            new RateLimiterFactory(['id' => 'client', 'policy' => 'sliding_window', 'limit' => $perClient, 'interval' => '1 hour'], $limits),
            new RateLimiterFactory(['id' => 'address', 'policy' => 'sliding_window', 'limit' => 3, 'interval' => '1 day'], $limits),
            'secret',
        ));
    }

    private function pages(): PrivacyPages
    {
        $tokens = self::createStub(CsrfTokenManagerInterface::class);
        $tokens->method('getToken')->willReturn(new CsrfToken('phil_privacy', 'tok'));
        $tokens->method('isTokenValid')->willReturnCallback(static fn (CsrfToken $token): bool => 'tok' === $token->getValue());
        $twig = new Environment(new ArrayLoader([
            '@PhilPrivacy/guest/request.html.twig' => 'guest/request|{{ error }}',
            '@PhilPrivacy/guest/sent.html.twig' => 'guest/sent|',
            '@PhilPrivacy/guest/confirm.html.twig' => 'guest/confirm|{{ id }}|{{ kind }}|{{ token }}|{{ incomplete ? "yes" : "no" }}',
            '@PhilPrivacy/guest/erased.html.twig' => 'guest/erased|',
            '@PhilPrivacy/guest/invalid.html.twig' => 'guest/invalid|',
        ]), ['strict_variables' => true]);

        return new PrivacyPages($twig, $tokens, self::createStub(UrlGeneratorInterface::class), 'layout');
    }
}
