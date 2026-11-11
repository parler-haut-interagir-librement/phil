<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Tests\Unit\Infrastructure;

use Closure;
use Phil\WellKnownBundle\Application\Contract\WellKnownDocument;
use Phil\WellKnownBundle\Infrastructure\Http\WellKnownController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[CoversClass(WellKnownController::class)]
final class WellKnownControllerTest extends TestCase
{
    public function testADocumentIsCachedAndNotIndexed(): void
    {
        $response = $this->controller(600)($this->request('ok'));

        self::assertSame([200, 'body', 'noindex'], [$response->getStatusCode(), $response->getContent(), $response->headers->get('X-Robots-Tag')]);
        self::assertSame([true, 600], [$response->headers->hasCacheControlDirective('public'), $response->getMaxAge()]);
        self::assertSame('"'.hash('xxh128', 'body').'"', $response->getEtag());
    }

    public function testARequestThatHoldsItGetsA304(): void
    {
        $request = $this->request('ok');
        $request->headers->set('If-None-Match', '"'.hash('xxh128', 'body').'"');

        $response = $this->controller(600)($request);

        self::assertSame([304, ''], [$response->getStatusCode(), $response->getContent()]);
    }

    public function testNoSharedCacheAtZero(): void
    {
        $response = $this->controller(0)($this->request('ok'));

        self::assertFalse($response->headers->hasCacheControlDirective('public'));
        self::assertNull($response->getMaxAge());
        self::assertSame('"'.hash('xxh128', 'body').'"', $response->getEtag());
        self::assertSame('noindex', $response->headers->get('X-Robots-Tag'));
    }

    public function testADocumentKeepsItsOwnCache(): void
    {
        $controller = $this->controller(600);

        $ownAge = $controller($this->request('own-age'));
        self::assertSame([60, null], [$ownAge->getMaxAge(), $ownAge->getEtag()]);
        self::assertSame('noindex', $ownAge->headers->get('X-Robots-Tag'));

        $noStore = $controller($this->request('no-store'));
        self::assertSame([null, null], [$noStore->getMaxAge(), $noStore->getEtag()]);
        self::assertFalse($noStore->headers->hasCacheControlDirective('public'));
    }

    public function testAnErrorIsNotCached(): void
    {
        $response = $this->controller(600)($this->request('missing'));

        self::assertSame([404, null, null, 'noindex'], [$response->getStatusCode(), $response->getMaxAge(), $response->getEtag(), $response->headers->get('X-Robots-Tag')]);
        self::assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    public function testARedirectIsNotCached(): void
    {
        $response = $this->controller(600)($this->request('redirect'));

        self::assertSame([302, null, null], [$response->getStatusCode(), $response->getMaxAge(), $response->getEtag()]);
    }

    public function testAnUnknownDocumentIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains('No well-known document is served here.');

        $this->controller(600)($this->request('nope'));
    }

    public function testAServiceThatIsNoDocumentIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller(600)($this->request('not-a-document'));
    }

    public function testARequestWithoutADocumentIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $request = new Request();
        $request->attributes->set(WellKnownController::DOCUMENT, ['ok']);
        $this->controller(600)($request);
    }

    private function request(string $document): Request
    {
        $request = new Request();
        $request->attributes->set(WellKnownController::DOCUMENT, $document);

        return $request;
    }

    private function controller(int $maxAge): WellKnownController
    {
        $document = static fn (Closure $serve): Closure => static fn (): WellKnownDocument => new readonly class($serve) implements WellKnownDocument {
            /**
             * @param Closure(): Response $response
             */
            public function __construct(
                private Closure $response,
            ) {
            }

            public function serve(Request $request): Response
            {
                return ($this->response)();
            }
        };

        return new WellKnownController(new ServiceLocator([
            'ok' => $document(static fn (): Response => new Response('body')),
            'own-age' => $document(static function (): Response {
                $response = new Response('body');
                $response->setMaxAge(60);

                return $response;
            }),
            'no-store' => $document(static fn (): Response => new Response('body', Response::HTTP_OK, ['Cache-Control' => 'no-store'])),
            'missing' => $document(static fn (): Response => new Response('', Response::HTTP_NOT_FOUND)),
            'redirect' => $document(static fn (): Response => new Response('', Response::HTTP_FOUND, ['Location' => '/x'])),
            'not-a-document' => static fn (): stdClass => new stdClass(),
        ]), $maxAge);
    }
}
