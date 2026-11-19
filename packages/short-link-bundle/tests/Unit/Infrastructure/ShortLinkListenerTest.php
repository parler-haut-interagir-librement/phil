<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\RedirectsBundle\Domain\Enum\QueryPolicy;
use Phil\ShortLinkBundle\Application\Contract\VisitorClassifier;
use Phil\ShortLinkBundle\Application\ShortLinkVisit;
use Phil\ShortLinkBundle\Domain\Enum\VisitorClass;
use Phil\ShortLinkBundle\Domain\Model\ShortLink;
use Phil\ShortLinkBundle\Domain\ValueObject\LinkTerms;
use Phil\ShortLinkBundle\Infrastructure\Http\ShortLinkListener;
use Phil\ShortLinkBundle\Tests\Unit\Fixture\Links;
use Phil\ShortLinkBundle\Tests\Unit\Fixture\RecordingRecorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(ShortLinkListener::class)]
final class ShortLinkListenerTest extends TestCase
{
    private const string BROWSER = 'Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0';

    private Links $fixture;

    protected function setUp(): void
    {
        $this->fixture = new Links();
        $this->fixture->repository->save(new ShortLink('abc123', 'https://example.com/page', new DateTimeImmutable('2026-11-01 UTC')));
        $this->fixture->repository->save(new ShortLink('closed', 'https://example.com/page', new DateTimeImmutable('2026-11-01 UTC'), new LinkTerms(enabled: false)));
    }

    public function testAShortLinkRedirectsWithoutBeingStoredAndTellsTheRecordersAfterTheResponse(): void
    {
        $recorder = new RecordingRecorder();
        $listener = new ShortLinkListener($this->fixture->resolver(), [$recorder]);
        $event = $this->event('https://go.example.com/abc123');
        $listener->onKernelRequest($event);
        $response = $event->getResponse();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame([302, 'https://example.com/page'], [$response->getStatusCode(), $response->getTargetUrl()]);
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame('noindex', $response->headers->get('X-Robots-Tag'));
        self::assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        self::assertSame(0, $recorder->told(), 'nothing is told before the response has gone');

        $listener->onKernelTerminate();
        self::assertEquals([new ShortLinkVisit(1, 'abc123', 'go.example.com', null)], $recorder->visits);
        $listener->onKernelTerminate();
        self::assertSame(1, $recorder->told(), 'a visit is told once');
    }

    /**
     * A machine is redirected the same way, counted apart, and told to the recorders with its class.
     */
    public function testAVisitIsClassifiedFromItsHeadersAndOnlyItsClassGoesOn(): void
    {
        $recorder = new RecordingRecorder();
        $listener = new ShortLinkListener($this->fixture->resolver(), [$recorder]);
        $visits = [
            [['HTTP_USER_AGENT' => self::BROWSER, 'HTTP_SEC_PURPOSE' => 'prefetch;prerender'], VisitorClass::Prefetch],
            [['HTTP_USER_AGENT' => self::BROWSER, 'HTTP_PURPOSE' => 'prefetch'], VisitorClass::Prefetch],
            [['HTTP_USER_AGENT' => self::BROWSER, 'HTTP_X_PURPOSE' => 'preview'], VisitorClass::Prefetch],
            [['HTTP_USER_AGENT' => self::BROWSER, 'HTTP_X_MOZ' => 'prefetch'], VisitorClass::Prefetch],
            [['HTTP_USER_AGENT' => self::BROWSER, 'HTTP_SEC_PURPOSE' => ''], VisitorClass::Human],
            [['HTTP_USER_AGENT' => 'Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)'], VisitorClass::LinkPreview],
            [['HTTP_USER_AGENT' => 'curl/8.7.1'], VisitorClass::Robot],
            [[], VisitorClass::Robot],
        ];

        foreach ($visits as [$headers, $class]) {
            $request = Request::create('https://go.example.com/abc123', server: $headers);

            if (!isset($headers['HTTP_USER_AGENT'])) {
                $request->headers->remove('User-Agent');
            }

            $event = new RequestEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST);
            $listener->onKernelRequest($event);
            self::assertSame(302, $event->getResponse()?->getStatusCode());
            $listener->onKernelTerminate();
            self::assertSame($class, array_last($recorder->visits)?->visitor);
        }

        self::assertSame([1, 7], [$this->fixture->repository->find(1)?->visits(), $this->fixture->repository->find(1)?->robotVisits()]);
    }

    public function testTheClassifierIsAPort(): void
    {
        $classifier = new class implements VisitorClassifier {
            /** @var list<array{?string, ?string}> */
            public array $asked = [];

            public function classify(?string $userAgent, ?string $purpose): VisitorClass
            {
                $this->asked[] = [$userAgent, $purpose];

                return VisitorClass::LinkPreview;
            }
        };
        $recorder = new RecordingRecorder();
        $listener = new ShortLinkListener($this->fixture->resolver(), [$recorder], null, $classifier);
        $listener->onKernelRequest($this->event('https://go.example.com/abc123', 'GET', ['HTTP_USER_AGENT' => 'Agent', 'HTTP_SEC_PURPOSE' => 'a', 'HTTP_PURPOSE' => 'b', 'HTTP_X_PURPOSE' => 'c', 'HTTP_X_MOZ' => 'd']));
        $listener->onKernelTerminate();

        $listener->onKernelRequest($this->event('https://go.example.com/abc123', 'GET', ['HTTP_USER_AGENT' => 'Agent', 'HTTP_SEC_PURPOSE' => '']));
        $listener->onKernelTerminate();

        self::assertSame([['Agent', 'a,b,c,d'], ['Agent', null]], $classifier->asked);
        self::assertSame(VisitorClass::LinkPreview, $recorder->visits[0]->visitor ?? null);
    }

    public function testHeadIsAnsweredLikeGet(): void
    {
        $event = $this->event('https://go.example.com/abc123', 'HEAD');
        new ShortLinkListener($this->fixture->resolver())->onKernelRequest($event);

        self::assertSame(302, $event->getResponse()?->getStatusCode());
    }

    public function testAnotherHostGoesOnUntouched(): void
    {
        $event = $this->event('https://www.example.com/abc123', 'POST');
        new ShortLinkListener($this->fixture->resolver())->onKernelRequest($event);

        self::assertNull($event->getResponse());
        self::assertSame([], $this->fixture->repository->lookups);
    }

    public function testASubRequestGoesOnUntouched(): void
    {
        $event = new RequestEvent($this->kernel(), Request::create('https://go.example.com/abc123'), HttpKernelInterface::SUB_REQUEST);
        new ShortLinkListener($this->fixture->resolver())->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testAnotherMethodIsRefusedBeforeTheLinkIsRead(): void
    {
        try {
            new ShortLinkListener($this->fixture->resolver())->onKernelRequest($this->event('https://go.example.com/abc123', 'POST'));
            self::fail('A POST was answered.');
        } catch (MethodNotAllowedHttpException $methodNotAllowedHttpException) {
            self::assertSame('GET, HEAD', $methodNotAllowedHttpException->getHeaders()['Allow']);
        }

        self::assertSame([], $this->fixture->repository->lookups);
    }

    public function testAnUnknownCodeIsNotFoundAndAClosedOneIsGone(): void
    {
        $listener = new ShortLinkListener($this->fixture->resolver());

        try {
            $listener->onKernelRequest($this->event('https://go.example.com/nothing'));
            self::fail('An unknown code was answered.');
        } catch (NotFoundHttpException $notFoundHttpException) {
            self::assertSame('There is no such short link.', $notFoundHttpException->getMessage());
        }

        try {
            $listener->onKernelRequest($this->event('https://go.example.com/closed'));
            self::fail('A closed link was answered.');
        } catch (GoneHttpException $goneHttpException) {
            self::assertSame('The short link is no longer in force.', $goneHttpException->getMessage());
        }
    }

    public function testARecorderThatFailsIsLoggedAndTheOthersAreStillTold(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{string, string, array<array-key, mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [\is_string($level) ? $level : '', (string) $message, $context];
            }
        };
        $after = new RecordingRecorder();
        $listener = new ShortLinkListener($this->fixture->resolver(), [new RecordingRecorder(true), $after], $logger);
        $listener->onKernelRequest($this->event('https://go.example.com/abc123'));
        $listener->onKernelTerminate();

        self::assertCount(1, $after->visits);
        self::assertCount(1, $logger->records);
        self::assertSame(['error', 'A short link visit could not be recorded by {recorder}: {message}', RecordingRecorder::class, 'The recorder is down.'], [$logger->records[0][0], $logger->records[0][1], $logger->records[0][2]['recorder'], $logger->records[0][2]['message']]);

        $quiet = new ShortLinkListener($this->fixture->resolver(), [new RecordingRecorder(true)]);
        $quiet->onKernelRequest($this->event('https://go.example.com/abc123'));
        $quiet->onKernelTerminate();
    }

    public function testTheStateOfTheRequestIsDroppedEvenWhenTheLoggerFails(): void
    {
        $logger = new class extends AbstractLogger {
            public function log($level, string|Stringable $message, array $context = []): void
            {
                throw new RuntimeException('The log is down.');
            }
        };
        $after = new RecordingRecorder();
        $listener = new ShortLinkListener($this->fixture->resolver(), [new RecordingRecorder(true), $after], $logger);
        $listener->onKernelRequest($this->event('https://go.example.com/abc123'));

        try {
            $listener->onKernelTerminate();
            self::fail('The failure of the logger was hidden.');
        } catch (RuntimeException) {
            self::assertSame([], $after->visits);
        }

        $listener->onKernelTerminate();
        self::assertSame([], $after->visits, 'the visit is not told again');
    }

    public function testTheQueryOfTheRequestGoesToTheLocation(): void
    {
        $this->fixture->repository->save(new ShortLink('keeper', 'https://example.com/page', new DateTimeImmutable('2026-11-01 UTC'), new LinkTerms(query: QueryPolicy::Keep)));
        $event = $this->event('https://go.example.com/keeper?utm=x');
        new ShortLinkListener($this->fixture->resolver())->onKernelRequest($event);

        self::assertSame('https://example.com/page?utm=x', $event->getResponse()?->headers->get('Location'));
    }

    public function testAResetForgetsTheVisit(): void
    {
        $recorder = new RecordingRecorder();
        $listener = new ShortLinkListener($this->fixture->resolver(), [$recorder]);
        $listener->onKernelRequest($this->event('https://go.example.com/abc123'));
        $listener->reset();
        $listener->onKernelTerminate();

        self::assertSame([], $recorder->visits);
    }

    /**
     * @param array<string, string> $headers by their names in `$_SERVER`
     */
    private function event(string $url, string $method = 'GET', array $headers = ['HTTP_USER_AGENT' => self::BROWSER]): RequestEvent
    {
        return new RequestEvent($this->kernel(), Request::create($url, $method, server: $headers), HttpKernelInterface::MAIN_REQUEST);
    }

    private function kernel(): HttpKernelInterface
    {
        return new class implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };
    }
}
