<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\MediaBundle\Application\UrlSigner;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\ValueObject\FileInfo;
use Phil\MediaBundle\Infrastructure\Twig\MediaExtension;
use Phil\SocialCards\Card\CardContent;
use Phil\SocialCards\Card\CardGenerator;
use Phil\SocialCards\Card\GdCardRenderer;
use Phil\SocialCards\Card\InMemoryCardStore;
use Phil\SocialCards\Exception\InvalidShareMetadata;
use Phil\SocialCards\ShareType;
use Phil\SocialCardsBundle\Application\CardTemplates;
use Phil\SocialCardsBundle\Application\Contract\ShareMetadataSource;
use Phil\SocialCardsBundle\Application\SharePage;
use Phil\SocialCardsBundle\Application\SocialCards;
use Phil\SocialCardsBundle\Infrastructure\Http\ShareMetadataFactory;
use Phil\SocialCardsBundle\Infrastructure\Http\SocialCardController;
use Phil\SocialCardsBundle\Infrastructure\Media\MediaShareImages;
use Phil\SocialCardsBundle\Infrastructure\Twig\SocialMetaExtension;
use Phil\SocialCardsBundle\Tests\Support\MemoryRequests;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(ShareMetadataFactory::class)]
#[CoversClass(SocialMetaExtension::class)]
#[CoversClass(MediaShareImages::class)]
final class ShareMetadataFactoryTest extends TestCase
{
    private const string FONT = __DIR__.'/../../Fixtures/card-font.ttf';

    public function testTheLayersOfAPage(): void
    {
        $page = new SharePage();
        $page->set(['title' => 'From the request', 'type' => 'article']);

        $factory = new ShareMetadataFactory($page, $this->requests('https://example.com/blog/post?utm=1'), $this->urls(), ['site_name' => 'The site', 'locale' => 'fr_CA', 'image' => 'https://example.com/default.png', 'twitter_site' => null], [
            $this->source(['title' => 'From a source', 'description' => 'Its description', 'author' => null]),
            $this->source(['author' => 'An author']),
        ]);

        $meta = $factory->build(['description' => 'Overridden', 'author' => null]);
        self::assertNotNull($meta);
        self::assertSame(['From the request', 'Overridden', 'https://example.com/blog/post', 'fr_CA', 'The site', 'An author', ShareType::Article, 'https://example.com/default.png'], [$meta->title, $meta->description, $meta->url, $meta->locale, $meta->siteName, $meta->author, $meta->type, $meta->image?->url]);
        self::assertSame('https://elsewhere.example/', $factory->build(['url' => 'https://elsewhere.example/'])?->url);
    }

    public function testTheRequestGivesTheAddressAndTheLocale(): void
    {
        $request = Request::create('https://example.com/app.php/fr/page?x=1', server: ['SCRIPT_FILENAME' => '/var/www/app.php', 'SCRIPT_NAME' => '/app.php', 'PHP_SELF' => '/app.php/fr/page']);
        $request->setLocale('fr');

        $stack = new RequestStack([$request]);
        $stack->push(Request::create('https://example.com/_fragment'));

        $factory = new ShareMetadataFactory(new SharePage(), $stack, $this->urls(), [], [$this->source(['title' => 'T'])]);

        self::assertSame(['https://example.com/app.php/fr/page', 'fr'], [$factory->build()?->url, $factory->build()?->locale]);
        self::assertNull(new ShareMetadataFactory(new SharePage(), new RequestStack(), $this->urls())->build(['title' => 'Without a request']), 'Without a request nor an address, there is no page.');
    }

    public function testAPageWithoutATitleHasNoTags(): void
    {
        $factory = new ShareMetadataFactory(new SharePage(), $this->requests('https://example.com/'), $this->urls(), ['site_name' => 'Site']);
        $extension = new SocialMetaExtension($factory, new SharePage());

        self::assertNull($factory->build());
        self::assertNull($factory->build(['title' => ' ']));
        self::assertNull($factory->build(['title' => 3]));
        self::assertSame('', $extension->meta());
    }

    public function testThePageGetsItsCard(): void
    {
        $store = new InMemoryCardStore();
        $requests = new MemoryRequests();
        $templates = new CardTemplates(['default' => ['title_font' => self::FONT], 'dark' => ['title_font' => self::FONT, 'background' => '#000']], 'default');
        $generator = new CardGenerator(new GdCardRenderer(), $store);
        $cards = new SocialCards($templates, $generator, $requests, $store);
        $factory = new ShareMetadataFactory(new SharePage(), $this->requests('https://example.com/post'), $this->urls(), ['site_name' => 'The site'], [], $cards);

        $meta = $factory->build(['title' => 'A post', 'locale' => 'fr']);
        $fingerprint = $generator->fingerprint($templates->get(), new CardContent('A post', 'The site', 'fr'));
        self::assertSame(['https://example.com/social-cards/'.$fingerprint.'.png', 'A post', 1200, 630, 'image/png'], [$meta?->image?->url, $meta?->image?->alt, $meta?->image?->width, $meta?->image?->height, $meta?->image?->type]);
        self::assertSame('The site', $requests->find($fingerprint)?->content->subtitle);

        $described = $factory->build(['title' => 'A post', 'description' => 'Its summary', 'card_template' => 'dark']);
        $dark = $generator->fingerprint($templates->get('dark'), new CardContent('A post', 'Its summary', 'en'));
        self::assertSame('https://example.com/social-cards/'.$dark.'.png', $described?->image?->url);
        self::assertSame(['dark', 'en'], [$requests->find($dark)?->template, $requests->find($dark)?->content->locale]);

        self::assertNull($factory->build(['title' => 'No card', 'card' => false])?->image);
        self::assertSame('https://example.com/own.png', $factory->build(['title' => 'Own image', 'image' => 'https://example.com/own.png'])?->image?->url);
        self::assertNull($factory->build(['title' => 'Plain', 'card_template' => 3, 'card' => 'yes', 'locale' => null])?->description, 'A card template that is no string is the default one.');

        $bare = new ShareMetadataFactory(new SharePage(), new RequestStack(), $this->urls(), [], [], $cards);
        $plain = $bare->build(['title' => 'Bare', 'url' => 'https://example.com/bare']);
        $content = new CardContent('Bare', null, 'en');
        self::assertSame('https://example.com/social-cards/'.$generator->fingerprint($templates->get(), $content).'.png', $plain?->image?->url);
    }

    public function testTheTagsOfThePage(): void
    {
        $page = new SharePage();
        $extension = new SocialMetaExtension(new ShareMetadataFactory($page, $this->requests('https://example.com/post'), $this->urls()), $page);

        self::assertSame('', $extension->page(['title' => 'From a template']));
        self::assertSame(['title' => 'From a template'], $page->values());
        self::assertStringContainsString('<meta property="og:title" content="From a template">', $extension->meta());
        self::assertStringContainsString("<meta name=\"twitter:card\" content=\"summary\">\n<meta name=\"twitter:title\" content=\"Another\">", $extension->meta(['title' => 'Another']));
    }

    public function testAMediumIsTheImage(): void
    {
        $routes = new RouteCollection();
        $routes->add('phil_media_original', new Route('/media/{id}/{version}'));

        $context = new RequestContext('', 'GET', 'example.com', 'https');
        $clock = new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-11-08 12:00 UTC');
            }
        };
        $media = new MediaShareImages(new MediaExtension(new UrlGenerator($routes, $context), new UrlSigner('secret'), $clock));
        $photo = Media::upload(FileInfo::of('photo.jpg', 'image/jpeg', 1000, 1600, 900), null, new DateTimeImmutable('2026-11-08 12:00 UTC'));
        $photo->describe('A photo', 'What it shows');

        $document = Media::upload(FileInfo::of('a.pdf', 'application/pdf', 1000), null, new DateTimeImmutable('2026-11-08 12:00 UTC'));

        $image = $media->image($photo);
        self::assertStringStartsWith('https://example.com/media/'.$photo->id.'/1?', (string) $image['url']);
        self::assertSame(['What it shows', 1600, 900, 'image/jpeg'], [$image['alt'], $image['width'], $image['height'], $image['type']]);
        self::assertSame([null, null, null, null], [$media->image($document)['alt'], $media->image($document)['width'], $media->image($document)['height'], $media->image($document)['type']]);

        $factory = new ShareMetadataFactory(new SharePage(), $this->requests('https://example.com/post'), $this->urls(), [], [], null, $media);
        self::assertSame(['What it shows', 1600], [$factory->build(['title' => 'T', 'image' => $photo])?->image?->alt, $factory->build(['title' => 'T', 'image' => $photo])?->image?->width]);
        self::assertNull(new ShareMetadataFactory(new SharePage(), $this->requests('https://example.com/post'), $this->urls())->build(['title' => 'T'])?->image);

        $this->expectException(InvalidShareMetadata::class);
        $this->expectExceptionMessageIsOrContains('The image of a shared page is an address, a ShareImage or an array; Phil\\MediaBundle\\Domain\\Model\\Media is given.');
        new ShareMetadataFactory(new SharePage(), $this->requests('https://example.com/post'), $this->urls())->build(['title' => 'T', 'image' => $photo]);
    }

    private function requests(string $url): RequestStack
    {
        return new RequestStack([Request::create($url)]);
    }

    private function urls(): UrlGenerator
    {
        $routes = new RouteCollection();
        $routes->add(SocialCardController::ROUTE, new Route('/social-cards/{fingerprint}.png'));

        return new UrlGenerator($routes, new RequestContext('', 'GET', 'example.com', 'https'));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function source(array $values): ShareMetadataSource
    {
        return new readonly class($values) implements ShareMetadataSource {
            /**
             * @param array<string, mixed> $values
             */
            public function __construct(
                private array $values,
            ) {
            }

            public function values(): array
            {
                return $this->values;
            }
        };
    }
}
