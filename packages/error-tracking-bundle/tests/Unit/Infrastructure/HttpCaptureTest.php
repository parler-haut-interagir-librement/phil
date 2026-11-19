<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Infrastructure;

use Phil\ErrorTracking\Source;
use Phil\ErrorTrackingBundle\Application\BrowserErrorParser;
use Phil\ErrorTrackingBundle\Application\BrowserErrorReceiver;
use Phil\ErrorTrackingBundle\Infrastructure\Http\BrowserErrorController;
use Phil\ErrorTrackingBundle\Infrastructure\Http\ExceptionListener;
use Phil\ErrorTrackingBundle\Infrastructure\Http\RequestSummaries;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\Tracking;
use Phil\IpAnonymizer\Truncation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

#[CoversClass(RequestSummaries::class)]
#[CoversClass(ExceptionListener::class)]
#[CoversClass(BrowserErrorController::class)]
final class HttpCaptureTest extends TestCase
{
    public function testARequestIsSummarized(): void
    {
        $request = Request::create('https://example.com/orders/12?token=abc', 'POST', [], [], [], ['REMOTE_ADDR' => '192.0.2.77', 'HTTP_ACCEPT' => 'text/html', 'HTTP_COOKIE' => 'PHPSESSID=x'], 'secret body');
        $request->attributes->set('_route', 'order_show');

        $summary = new RequestSummaries(new Truncation())->summarize($request);

        self::assertSame('POST', $summary->method);
        self::assertSame('https://example.com/orders/12?token=abc', $summary->url);
        self::assertSame('order_show', $summary->route);
        self::assertSame('192.0.2.0', $summary->clientAddress);
        self::assertSame('text/html', $summary->headers['accept']);
        self::assertSame('example.com', $summary->headers['host']);
        self::assertSame('PHPSESSID=x', $summary->headers['cookie']);
        self::assertNotContains('secret body', $summary->headers);

        $request->attributes->set('_route', ['not', 'a', 'name']);
        self::assertNull(new RequestSummaries(new Truncation())->summarize($request)->route);
    }

    public function testHeadersOfSeveralValuesAreJoined(): void
    {
        $request = Request::create('/a');
        $request->headers->set('Accept-Language', ['fr-CA', 'en']);
        $request->headers->add(['X-Partial' => ['a', null]]);

        $headers = new RequestSummaries(new Truncation())->summarize($request)->headers;

        self::assertSame('fr-CA, en', $headers['accept-language']);
        self::assertSame('a', $headers['x-partial']);
    }

    public function testBehindAProxyTheRawClientAddressIsNeverKept(): void
    {
        Request::setTrustedProxies(['10.0.0.1'], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_FORWARDED);

        try {
            $request = Request::create('https://example.com/a', 'GET', [], [], [], [
                'REMOTE_ADDR' => '10.0.0.1',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.9, 10.0.0.1',
                'HTTP_FORWARDED' => 'for=203.0.113.9;proto=https',
                'HTTP_X_REAL_IP' => '203.0.113.9',
                'HTTP_CF_CONNECTING_IP' => '203.0.113.9',
                'HTTP_TRUE_CLIENT_IP' => '203.0.113.9',
                'HTTP_X_CLIENT_IP' => '203.0.113.9',
                'HTTP_ACCEPT' => 'text/html',
            ]);

            $summary = new RequestSummaries(new Truncation())->summarize($request);
        } finally {
            Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        }

        self::assertSame('203.0.113.0', $summary->clientAddress);
        self::assertSame('text/html', $summary->headers['accept']);

        foreach (['x-forwarded-for', 'forwarded', 'x-real-ip', 'cf-connecting-ip', 'true-client-ip', 'x-client-ip'] as $name) {
            self::assertArrayNotHasKey($name, $summary->headers);
        }

        foreach ($summary->headers as $value) {
            self::assertStringNotContainsString('203.0.113.9', $value);
        }
    }

    public function testTheExceptionOfARequestIsCapturedWithTheRequest(): void
    {
        $tracking = new Tracking();
        $listener = new ExceptionListener($tracking->capture, new RequestSummaries(new Truncation()));
        $listener->onKernelException($this->event(new RuntimeException('Export failed'), Request::create('https://example.com/export?page=2')));

        $event = $tracking->store->onlyEvent();
        self::assertSame(Source::Http, $event->source);
        self::assertSame('https://example.com/export?page=[Filtered]', $event->request?->url);
    }

    #[TestWith([404])]
    #[TestWith([499])]
    public function testClientErrorsAreLeftOut(int $status): void
    {
        $tracking = new Tracking();
        new ExceptionListener($tracking->capture, new RequestSummaries(new Truncation()))->onKernelException($this->event(new HttpException($status), Request::create('/a')));

        self::assertSame([], $tracking->store->groups);
    }

    public function testServerErrorsAndAskedClientErrorsAreCaptured(): void
    {
        $tracking = new Tracking();
        new ExceptionListener($tracking->capture, new RequestSummaries(new Truncation()))->onKernelException($this->event(new HttpException(500), Request::create('/a')));
        new ExceptionListener($tracking->capture, new RequestSummaries(new Truncation()), true)->onKernelException($this->event(new NotFoundHttpException('gone'), Request::create('/b')));

        self::assertCount(2, $tracking->store->groups);
    }

    public function testTheBrowserEndpoint(): void
    {
        $tracking = new Tracking();
        $body = '{"message":"x is undefined","url":"https://example.com/cart"}';
        $controller = new BrowserErrorController(new BrowserErrorReceiver(new BrowserErrorParser(), $tracking->factory, $tracking->capture, new Truncation(), 'secret', \strlen($body), [], 's3cret'));
        $request = Request::create('https://example.com/_errors/browser', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'https://example.com', 'HTTP_X_ERROR_TRACKING_TOKEN' => 's3cret', 'HTTP_USER_AGENT' => 'Firefox/140', 'REMOTE_ADDR' => '192.0.2.5'], $body);
        $response = $controller($request);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', $response->getContent());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertSame(['user-agent' => 'Firefox/140'], $tracking->store->onlyEvent()->request?->headers);
        self::assertSame('192.0.2.0', $tracking->store->onlyEvent()->request->clientAddress);

        $larger = Request::create('https://example.com/_errors/browser', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'https://example.com'], $body.'  ');
        self::assertSame(413, $controller($larger)->getStatusCode());
        $untyped = Request::create('https://example.com/_errors/browser', 'POST', [], [], [], ['HTTP_ORIGIN' => 'https://example.com'], $body);
        self::assertSame(415, $controller($untyped)->getStatusCode());
        $wrong = Request::create('https://example.com/_errors/browser', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'https://example.com'], $body);
        self::assertSame(403, $controller($wrong)->getStatusCode());
        self::assertSame('X-Error-Tracking-Token', BrowserErrorController::TOKEN_HEADER);
        self::assertSame('phil_error_tracking_browser', BrowserErrorController::ROUTE);
    }

    private function event(Throwable $throwable, Request $request): ExceptionEvent
    {
        $kernel = self::createStub(HttpKernelInterface::class);

        return new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $throwable);
    }
}
