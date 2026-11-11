<?php

declare(strict_types=1);

namespace Phil\SitemapBundle\Tests\Unit;

use DateTimeImmutable;
use Phil\Sitemap\Url;
use Phil\Sitemap\UrlContainer;
use Phil\Sitemap\UrlProvider;
use Phil\SitemapBundle\Infrastructure\Http\SitemapController;
use Phil\SitemapBundle\Infrastructure\Sitemaps;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(SitemapController::class)]
final class SitemapControllerTest extends TestCase
{
    /**
     * The latest `lastmod` of the pages, wherever it is in the file, is the file's own.
     */
    public function testTheLatestLastmodIsTheFilesOwn(): void
    {
        $response = $this->file(3600);

        self::assertSame('Wed, 04 Nov 2026 12:00:00 GMT', $response->headers->get('Last-Modified'));
        self::assertSame(['public', 3600], [$response->headers->hasCacheControlDirective('public') ? 'public' : 'private', $response->getMaxAge()]);
    }

    public function testAMaxAgeOfZeroKeepsTheFileInNoCache(): void
    {
        $response = $this->file(0);

        self::assertFalse($response->headers->hasCacheControlDirective('public'));
        self::assertNull($response->getMaxAge());
    }

    public function testTheFileIsXmlWithAHashOfItsContentAsETag(): void
    {
        $response = $this->file(0);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/xml; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('"'.hash('xxh128', (string) $response->getContent()).'"', $response->getEtag());
    }

    public function testARequestThatHoldsTheFileIsANotModified(): void
    {
        $etag = (string) $this->file(0)->getEtag();
        $request = Request::create('/sitemap.news.xml');
        $request->headers->set('If-None-Match', $etag);

        $response = $this->file(0, $request);

        self::assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }

    public function testAPartTheSectionDoesNotHaveIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains('The sitemap has no file "news_3".');

        $this->file(0, name: 'news_3');
    }

    private function file(int $maxAge, ?Request $request = null, string $name = 'news'): Response
    {
        $pages = new class implements UrlProvider {
            public function provide(UrlContainer $urls, ?string $section): void
            {
                $urls->add(new Url('https://example.com/b', new DateTimeImmutable('2026-11-02 12:00 UTC')), 'news');
                $urls->add(new Url('https://example.com/c', new DateTimeImmutable('2026-11-04 08:00 -04:00')), 'news');
                $urls->add(new Url('https://example.com/d'), 'news');
                $urls->add(new Url('https://example.com/a', new DateTimeImmutable('2026-11-01 12:00 UTC')), 'news');
            }
        };

        $controller = new SitemapController(new Sitemaps([$pages]), self::createStub(UrlGeneratorInterface::class), 50000, $maxAge);

        return $controller->file($request ?? Request::create('/sitemap.news.xml'), $name);
    }
}
