<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Tests\Functional;

use Override;
use Phil\SocialCardsBundle\Infrastructure\Http\LinkPreviewController;
use Phil\SocialCardsBundle\Infrastructure\Twig\Components\LinkPreviewComponent;
use Phil\SocialCardsBundle\PhilSocialCardsBundle;
use Phil\SocialCardsBundle\Tests\Functional\App\TestKernel;
use Phil\SocialCardsBundle\Tests\Support\Directories;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(PhilSocialCardsBundle::class)]
#[CoversClass(LinkPreviewComponent::class)]
#[CoversClass(LinkPreviewController::class)]
final class SocialCardsBundleTest extends WebTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        Directories::remove(TestKernel::cardDirectory());
    }

    public function testAPageHasItsTagsAndItsCard(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'example.com', 'HTTPS' => 'on']);
        $client->request('GET', '/blog/post?utm_source=x');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('<link rel="canonical" href="https://example.com/blog/post">', $html);
        self::assertStringContainsString('<meta property="og:title" content="Un « article »">', $html);
        self::assertStringContainsString('<meta property="og:type" content="article">', $html);
        self::assertStringContainsString('<meta property="og:site_name" content="Le site">', $html);
        self::assertStringContainsString('<meta name="twitter:site" content="@lesite">', $html);
        self::assertStringContainsString('<meta property="og:locale" content="en">', $html);
        self::assertSame(1, preg_match('{<meta property="og:image" content="https://example\.com/social-cards/([0-9a-f]{32})\.png">}', $html, $match));
        self::assertStringContainsString('<meta property="og:image:width" content="1200">', $html);
        self::assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);

        // The card is drawn by the handler of its message, routed here to the synchronous transport.
        self::assertFileExists(TestKernel::cardDirectory().'/'.substr($match[1], 0, 2).'/'.$match[1].'.png');
        self::assertFileExists(TestKernel::cardDirectory().'/'.substr($match[1], 0, 2).'/'.$match[1].'.json');

        // The image is served from the store.
        $client->request('GET', '/social-cards/'.$match[1].'.png');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
        self::assertStringStartsWith("\x89PNG", $client->getResponse()->getContent());
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('immutable'));

        $client->request('GET', '/social-cards/'.$match[1].'.png', server: ['HTTP_IF_NONE_MATCH' => '"'.$match[1].'"']);
        self::assertResponseStatusCodeSame(304);
        $client->request('GET', '/social-cards/'.str_repeat('0', 32).'.png');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/social-cards/nope.png');
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheLinksArePreviewed(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'example.com', 'HTTPS' => 'on']);
        $client->request('GET', '/blog/post');

        $html = (string) preg_replace('/\s+/', ' ', (string) $client->getResponse()->getContent());

        self::assertStringContainsString('<a href="https://example.org/post" class="phil-link-preview" rel="noopener nofollow" >', $html);
        self::assertStringContainsString('<img class="phil-link-preview__image" src="https://example.org/i.png" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer">', $html);
        self::assertStringContainsString('<span class="phil-link-preview__site">Ailleurs</span> <strong class="phil-link-preview__title">Un billet</strong> <span class="phil-link-preview__description">Le résumé</span>', $html);
        self::assertStringContainsString('<a href="https://example.org/dead" class="phil-link-preview phil-link-preview--plain" rel="noopener nofollow" >https://example.org/dead</a>', $html);
        self::assertStringNotContainsString('javascript:', $html, 'An address that cannot be fetched is no link.');
        self::assertStringNotContainsString('data:text', $html, 'An address that cannot be fetched is no link.');
        self::assertStringContainsString('class="phil-link-preview" rel="noopener nofollow" class="compact"', $html);
        self::assertSame(1, substr_count($html, 'phil-link-preview__image'), 'The third preview shows no image.');
        self::assertStringContainsString('<a href="https://example.net/kept" class="phil-link-preview" rel="noopener nofollow" > <span class="phil-link-preview__body"> <span class="phil-link-preview__site">Kept</span> <strong class="phil-link-preview__title">Kept title</strong>', $html, 'A kept preview is shown as it was kept.');
    }

    public function testThePastedLinksArePreviewedWithinTheRateLimit(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'example.com', 'HTTPS' => 'on']);
        // The limiter counts in a pool of the cache directory, emptied before the test.
        $pool = self::getContainer()->get('test.limiter_pool');
        self::assertInstanceOf(CacheItemPoolInterface::class, $pool);
        $pool->clear();

        $client->request('GET', '/social-cards/link-preview?url=http%3A%2F%2F10.0.0.1%2F');
        self::assertResponseStatusCodeSame(400);
        $client->request('GET', '/social-cards/link-preview');
        self::assertResponseStatusCodeSame(400);

        $client->request('GET', '/social-cards/link-preview?url=https%3A%2F%2Fexample.org%2Fpost');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $preview = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($preview);
        self::assertSame(['https://example.org/post', 'Un billet', 'https://example.org/i.png'], [$preview['url'] ?? null, $preview['title'] ?? null, $preview['image'] ?? null]);
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('private'));
        self::assertSame('300', $client->getResponse()->headers->getCacheControlDirective('max-age'));
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');

        $client->request('GET', '/social-cards/link-preview?url=https%3A%2F%2Fexample.org%2Fdead');
        self::assertResponseStatusCodeSame(204);

        $client->request('GET', '/social-cards/link-preview?url=https%3A%2F%2Fexample.org%2Fpost');
        self::assertResponseStatusCodeSame(429);
        $retry = (int) $client->getResponse()->headers->get('Retry-After');
        self::assertGreaterThan(3500, $retry, 'The fixed window of an hour has just begun.');
        self::assertLessThanOrEqual(3601, $retry, 'Whole seconds, the end of the window rounded down and now rounded down.');
        $client->request('POST', '/social-cards/link-preview?url=https%3A%2F%2Fexample.org%2Fpost');
        self::assertResponseStatusCodeSame(405);
    }

    public function testTheCommandChecksAPage(): void
    {
        $application = new Application(self::bootKernel());
        $application->setAutoExit(false);

        $output = new BufferedOutput();

        $code = $application->run(new ArrayInput(['command' => 'phil:social-cards:check', 'page' => '/blog/post', '-v' => true]), $output);
        $written = (string) preg_replace('/\s+/', ' ', $output->fetch());

        self::assertSame(0, $code, $written);
        self::assertStringContainsString('https://example.com/blog/post', $written);
        self::assertStringContainsString('og:image', $written);
        self::assertStringContainsString('The tags are complete.', $written);
    }
}
