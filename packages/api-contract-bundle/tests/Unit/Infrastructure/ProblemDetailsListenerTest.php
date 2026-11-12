<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Unit\Infrastructure;

use Phil\ApiContract\Exception\ApiContractException;
use Phil\ApiContract\Problem\ProblemDetails;
use Phil\ApiContractBundle\Infrastructure\Http\ProblemDetailsListener;
use Phil\ApiContractBundle\Infrastructure\Http\ProblemDetailsResponse;
use Phil\ApiContractBundle\Infrastructure\Http\ProblemException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

#[CoversClass(ProblemDetailsListener::class)]
#[CoversClass(ProblemDetailsResponse::class)]
#[CoversClass(ProblemException::class)]
final class ProblemDetailsListenerTest extends TestCase
{
    public function testAProblemIsAnsweredWherever(): void
    {
        $problem = ProblemDetails::create(429, 'https://example.com/problems/slow-down', 'Slow down', 'Ten a minute.', extensions: ['limit' => 10]);
        $previous = new RuntimeException('x');
        $exception = new ProblemException($problem, ['Retry-After' => '60'], $previous);
        $response = $this->handle(new ProblemDetailsListener(), $exception, '/page');

        self::assertInstanceOf(ProblemDetailsResponse::class, $response);
        self::assertSame([429, 'application/problem+json', '60'], [$response->getStatusCode(), $response->headers->get('Content-Type'), $response->headers->get('Retry-After')]);
        self::assertSame(['type' => 'https://example.com/problems/slow-down', 'status' => 429, 'title' => 'Slow down', 'detail' => 'Ten a minute.', 'limit' => 10], json_decode((string) $response->getContent(), true));
        self::assertSame(['Slow down', 429, $previous], [$exception->getMessage(), $exception->getCode(), $exception->getPrevious()]);
        self::assertInstanceOf(ApiContractException::class, $exception);
        self::assertSame(['about:blank', []], [new ProblemException(ProblemDetails::create(400))->getMessage(), new ProblemException(ProblemDetails::create(400))->headers]);

        $made = ProblemException::of(409, 'Busy', 'Later.', $previous);
        self::assertSame([409, 'Busy', 'Later.', $previous], [$made->problem->status, $made->problem->title, $made->problem->detail, $made->getPrevious()]);
        self::assertSame([null, null], [ProblemException::of(400)->problem->title, ProblemException::of(400)->getPrevious()]);
    }

    public function testUnderThePathsAnHttpExceptionIsItsBlankProblem(): void
    {
        $listener = new ProblemDetailsListener(['^/api/', '^/v2/']);
        $response = $this->handle($listener, new NotFoundHttpException('No such thing.'), '/api/orders');
        self::assertSame([404, ['type' => 'about:blank', 'status' => 404, 'title' => 'Not Found']], [$response?->getStatusCode(), json_decode((string) $response?->getContent(), true)]);

        $response = $this->handle($listener, new HttpException(405, headers: ['Allow' => 'GET']), '/v2/x');
        self::assertInstanceOf(Response::class, $response);
        self::assertSame([405, 'GET', 'Method Not Allowed'], [$response->getStatusCode(), $response->headers->get('Allow'), $this->title($response)]);

        $response = $this->handle($listener, new HttpException(499), '/api/x');
        self::assertSame(['type' => 'about:blank', 'status' => 499], json_decode((string) $response?->getContent(), true));

        self::assertNull($this->handle($listener, new NotFoundHttpException(), '/page/api/'));
        self::assertSame([400, 'Bad Request'], [$this->handle($listener, new HttpException(400), '/api/x')?->getStatusCode(), $this->title($this->handle($listener, new HttpException(400), '/api/x'))]);
        self::assertSame([429, 'Slow down'], [$this->handle($listener, ProblemException::of(429, 'Slow down'), '/api/x')?->getStatusCode(), $this->title($this->handle($listener, ProblemException::of(429, 'Slow down'), '/api/x'))]);
        self::assertNull($this->handle(new ProblemDetailsListener(), new NotFoundHttpException(), '/api/x'));
    }

    public function testUnderThePathsAnyOtherExceptionIsAnInternalError(): void
    {
        $response = $this->handle(new ProblemDetailsListener(['^/api/']), new RuntimeException('secret'), '/api/x');
        self::assertSame([500, ['type' => 'about:blank', 'status' => 500, 'title' => 'Internal Server Error']], [$response?->getStatusCode(), json_decode((string) $response?->getContent(), true)]);

        self::assertNull($this->handle(new ProblemDetailsListener(['^/api/'], true), new RuntimeException('secret'), '/api/x'));
        self::assertNull($this->handle(new ProblemDetailsListener(['^/api/']), new RuntimeException('secret'), '/x'));
        self::assertSame(500, $this->handle(new ProblemDetailsListener(['^/api/']), new HttpException(302), '/api/x')?->getStatusCode());
    }

    private function title(?Response $response): mixed
    {
        $problem = json_decode((string) $response?->getContent(), true);
        self::assertIsArray($problem);

        return $problem['title'] ?? null;
    }

    private function handle(ProblemDetailsListener $listener, Throwable $exception, string $path): ?Response
    {
        $event = new ExceptionEvent(self::createStub(HttpKernelInterface::class), Request::create($path), HttpKernelInterface::MAIN_REQUEST, $exception);
        $listener($event);

        return $event->getResponse();
    }
}
