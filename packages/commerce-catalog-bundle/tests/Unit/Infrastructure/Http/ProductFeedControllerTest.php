<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Infrastructure\Http;

use Override;
use Phil\CommerceCatalogBundle\Infrastructure\Http\ProductFeedController;
use Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds\FeedDefinition;
use Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds\ProductFeeds;
use Phil\CommerceCatalogBundle\Tests\Unit\Support\InMemoryListing;
use Phil\ProductFeeds\FeedChannel;
use Phil\ProductFeeds\FeedFormat;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Loader\PhpFileLoader;

#[CoversClass(ProductFeedController::class)]
final class ProductFeedControllerTest extends TestCase
{
    private string $directory = '';

    #[Override]
    protected function setUp(): void
    {
        $directory = sys_get_temp_dir().'/phil-catalog-served-'.bin2hex(random_bytes(6));
        mkdir($directory);
        $this->directory = (string) realpath($directory);
        file_put_contents($this->directory.'/google.xml', '<rss/>');
        file_put_contents($this->directory.'/meta.tsv', "id\ttitle\n");
        touch($this->directory.'/google.xml', 1_800_000_000);
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach (['google.xml', 'meta.tsv'] as $file) {
            if (is_file($this->directory.'/'.$file)) {
                unlink($this->directory.'/'.$file);
            }
        }

        rmdir($this->directory);
    }

    public function testThePublishedFileIsServedWithItsTypeAndCacheHeaders(): void
    {
        $response = $this->controller()(Request::create('/product-feeds/google'), 'google');

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame($this->directory.'/google.xml', $response->getFile()->getPathname());
        self::assertSame('application/rss+xml; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('max-age=600, public', $response->headers->get('Cache-Control'));
        self::assertSame('"'.hash('xxh128', '1800000000:6').'"', $response->getEtag());
        self::assertSame('Fri, 15 Jan 2027 08:00:00 GMT', $response->headers->get('Last-Modified'));
        self::assertNull($response->headers->get('Content-Disposition'));

        $tsv = $this->controller()(Request::create('/product-feeds/meta'), 'meta');
        self::assertSame('text/tab-separated-values; charset=UTF-8', $tsv->headers->get('Content-Type'));
        self::assertSame('max-age=3600, public', $tsv->headers->get('Cache-Control'));
    }

    public function testAReaderThatSendsTheTagBackIsAnsweredNotModified(): void
    {
        $etag = (string) $this->controller()(Request::create('/product-feeds/google'), 'google')->getEtag();
        $request = Request::create('/product-feeds/google', server: ['HTTP_IF_NONE_MATCH' => $etag]);

        self::assertSame(304, $this->controller()($request, 'google')->getStatusCode());

        $since = Request::create('/product-feeds/google', server: ['HTTP_IF_MODIFIED_SINCE' => 'Fri, 15 Jan 2027 08:00:00 GMT']);
        self::assertSame(304, $this->controller()($since, 'google')->getStatusCode());

        $changed = Request::create('/product-feeds/google', server: ['HTTP_IF_NONE_MATCH' => '"other"']);
        self::assertSame(200, $this->controller()($changed, 'google')->getStatusCode());
    }

    public function testAFeedNotDeclaredIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIs('No feed "other" is published.');

        $this->controller()(Request::create('/product-feeds/other'), 'other');
    }

    public function testAFeedNotYetPublishedIsNotFound(): void
    {
        unlink($this->directory.'/meta.tsv');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIs('No feed "meta" is published.');

        $this->controller()(Request::create('/product-feeds/meta'), 'meta');
    }

    public function testADirectoryAtThePathIsNotServed(): void
    {
        $feeds = new ProductFeeds([$this->definition('dir', $this->directory, FeedFormat::Xml)], new InMemoryListing());

        $this->expectException(NotFoundHttpException::class);

        new ProductFeedController($feeds)(Request::create('/product-feeds/dir'), 'dir');
    }

    public function testTheRouteNamesTheFeedOnly(): void
    {
        $routes = new PhpFileLoader(new FileLocator(\dirname(__DIR__, 4).'/config'))->load('routes.php');
        $route = $routes->get(ProductFeedController::ROUTE);

        self::assertNotNull($route);
        self::assertSame('/product-feeds/{feed}', $route->getPath());
        self::assertSame(ProductFeedController::class, $route->getDefault('_controller'));
        self::assertSame('[A-Za-z0-9_-]{1,64}', $route->getRequirement('feed'));
        self::assertSame(['GET', 'HEAD'], $route->getMethods());
    }

    private function controller(): ProductFeedController
    {
        return new ProductFeedController(new ProductFeeds([
            $this->definition('google', $this->directory.'/google.xml', FeedFormat::Xml, 600),
            $this->definition('meta', $this->directory.'/meta.tsv', FeedFormat::Tsv),
        ], new InMemoryListing()));
    }

    private function definition(string $name, string $path, FeedFormat $format, int $maxAge = FeedDefinition::DEFAULT_MAX_AGE): FeedDefinition
    {
        return new FeedDefinition($name, $path, $format, new FeedChannel('Shop', Url::fromString('https://shop.example/'), 'Dice'), 'https://shop.example/{sku}', maxAge: $maxAge);
    }
}
