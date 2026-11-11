<?php

declare(strict_types=1);

namespace Phil\SitemapBundle\Tests\Functional;

use DirectoryIterator;
use Override;
use Phil\SitemapBundle\PhilSitemapBundle;
use Phil\SitemapBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(PhilSitemapBundle::class)]
final class SitemapBundleTest extends WebTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testTheSitemapIsServed(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'example.com', 'HTTPS' => 'on']);

        $client->request('GET', '/sitemap.xml');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');
        $index = (string) $client->getResponse()->getContent();
        self::assertSame(4, substr_count($index, '<sitemap>'));
        foreach (['default', 'pages', 'articles_0', 'articles_1'] as $file) {
            self::assertStringContainsString('<loc>https://example.com/sitemap.'.$file.'.xml</loc>', $index);
        }

        // The pages of the routes come before those of the providers.
        self::assertLessThan(strpos($index, 'sitemap.articles_0.xml'), strpos($index, 'sitemap.default.xml'));

        $client->request('GET', '/sitemap.default.xml');
        $default = (string) $client->getResponse()->getContent();
        self::assertStringContainsString("<loc>https://example.com/</loc>\n    <lastmod>2026-11-01T08:00:00+00:00</lastmod>\n    <changefreq>daily</changefreq>\n    <priority>1.0</priority>", $default);
        self::assertStringContainsString('<loc>https://example.com/contact</loc>', $default);
        self::assertStringNotContainsString('cache', $default);
        self::assertStringNotContainsString('plain', $default);

        $client->request('GET', '/sitemap.pages.xml');
        $pages = (string) $client->getResponse()->getContent();
        self::assertStringContainsString("<loc>https://example.com/a-propos</loc>\n    <changefreq>yearly</changefreq>\n    <priority>0.5</priority>\n    <xhtml:link rel=\"alternate\" hreflang=\"fr\" href=\"https://example.com/a-propos\"/>\n    <xhtml:link rel=\"alternate\" hreflang=\"en\" href=\"https://example.com/about\"/>", $pages);
        self::assertSame(2, substr_count($pages, '<url>'));

        $client->request('GET', '/sitemap.articles_1.xml');
        self::assertStringContainsString('<image:loc>https://example.com/trois.png</image:loc>', (string) $client->getResponse()->getContent());
        self::assertSame(1, substr_count((string) $client->getResponse()->getContent(), '<url>'));

        foreach (['nope', 'articles_2', 'nope_1', 'articles_1_1'] as $missing) {
            $client->request('GET', '/sitemap.'.$missing.'.xml');
            self::assertResponseStatusCodeSame(404);
        }
    }

    /**
     * A served file may be kept by a shared cache, says when its pages last changed, and is not sent again to a client
     * that holds it.
     */
    public function testAServedSitemapIsCacheable(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'example.com', 'HTTPS' => 'on']);

        $client->request('GET', '/sitemap.articles_0.xml');

        $response = $client->getResponse();
        self::assertSame(['public', 3600], [$response->headers->hasCacheControlDirective('public') ? 'public' : 'private', $response->getMaxAge()]);
        self::assertSame('Wed, 04 Nov 2026 12:00:00 GMT', $response->headers->get('Last-Modified'));
        $etag = (string) $response->getEtag();
        self::assertNotSame('', $etag);

        $client->request('GET', '/sitemap.articles_0.xml', server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseStatusCodeSame(304);
        self::assertSame('', (string) $client->getResponse()->getContent());

        $client->request('GET', '/sitemap.xml', server: ['HTTP_IF_MODIFIED_SINCE' => 'Thu, 05 Nov 2026 00:00:00 GMT']);
        self::assertResponseStatusCodeSame(304);

        $client->request('GET', '/sitemap.pages.xml');
        self::assertFalse($client->getResponse()->headers->has('Last-Modified'), 'No page of the section says when it changed.');
    }

    public function testTheSitemapIsDumped(): void
    {
        $directory = sys_get_temp_dir().'/phil-sitemap-dump-'.bin2hex(random_bytes(4));
        mkdir($directory);
        touch($directory.'/sitemap.old.xml.gz');
        touch($directory.'/robots.txt');
        touch($directory.'/mysitemap.a.xml');
        touch($directory.'/sitemap.a.xml.bak');
        $application = new Application(self::bootKernel());
        $application->setAutoExit(false);

        $output = new BufferedOutput();

        self::assertSame(0, $application->run(new ArrayInput(['command' => 'phil:sitemap:dump', 'directory' => $directory, '--gzip' => true, '--base-url' => 'https://cdn.example.com/']), $output), $output->fetch());

        $files = [];

        foreach (new DirectoryIterator($directory) as $file) {
            if (!$file->isDot()) {
                $files[] = $file->getFilename();
            }
        }

        sort($files);
        self::assertSame(['mysitemap.a.xml', 'robots.txt', 'sitemap.a.xml.bak', 'sitemap.articles_0.xml.gz', 'sitemap.articles_1.xml.gz', 'sitemap.default.xml.gz', 'sitemap.pages.xml.gz', 'sitemap.xml'], $files);
        self::assertStringContainsString('<loc>https://cdn.example.com/sitemap.pages.xml.gz</loc>', (string) file_get_contents($directory.'/sitemap.xml'));
        self::assertStringContainsString('https://example.com/about', (string) gzdecode((string) file_get_contents($directory.'/sitemap.pages.xml.gz')));

        $code = $application->run(new ArrayInput(['command' => 'phil:sitemap:dump', 'directory' => $directory.'/plain/', '-v' => true]), $output = new BufferedOutput());
        $written = $this->withoutSpaces($output);
        self::assertSame(0, $code, $written);
        self::assertStringContainsString('<loc>https://example.com/sitemap.default.xml</loc>', (string) file_get_contents($directory.'/plain/sitemap.xml'));
        self::assertStringContainsString('sitemap.default.xml:2pages', $written);
        self::assertStringContainsString('4filesandtheirindex', $written);
        self::assertStringContainsString('<loc>https://example.com/</loc>', (string) file_get_contents($directory.'/plain/sitemap.default.xml'));

        new Filesystem()->remove($directory);
    }

    public function testADumpUnderTheRoutersLocalhostIsRefused(): void
    {
        $directory = sys_get_temp_dir().'/phil-sitemap-dump-'.bin2hex(random_bytes(4));
        $application = new Application(self::bootKernel(['environment' => 'localhost']));
        $application->setAutoExit(false);

        $code = $application->run(new ArrayInput(['command' => 'phil:sitemap:dump', 'directory' => $directory, '-v' => true]), $output = new BufferedOutput());

        $written = $this->withoutSpaces($output);
        self::assertSame(1, $code, $written);
        self::assertStringContainsString('framework.router.default_uri', $written);
        self::assertDirectoryDoesNotExist($directory);
        // A base URL does not move the pages: still on the router's http://localhost, they are refused, and nothing is written.
        self::assertSame(1, $application->run(new ArrayInput(['command' => 'phil:sitemap:dump', 'directory' => $directory, '--base-url' => 'http://app.localhost:8000']), $output = new BufferedOutput()));
        self::assertStringContainsString('isanabsoluteHTTPorHTTPSURL', $this->withoutSpaces($output));
        self::assertDirectoryDoesNotExist($directory);

        new Filesystem()->remove($directory);
    }

    /**
     * What a command wrote, without a single blank: a console block wraps at the terminal's width — `COLUMNS`, up to
     * 120 — and cuts a word longer than a line, wherever the temporary directory's length makes the sentence break.
     */
    private function withoutSpaces(BufferedOutput $output): string
    {
        return (string) preg_replace('/\s+/', '', $output->fetch());
    }
}
