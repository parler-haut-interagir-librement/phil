<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Application\UrlSigner;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\ValueObject\FileInfo;
use Phil\MediaBundle\Infrastructure\Doctrine\DoctrineMediaRepository;
use Phil\MediaBundle\Infrastructure\Image\GdImageProcessor;
use Phil\MediaBundle\Infrastructure\Storage\FilesystemStorage;
use Phil\MediaBundle\Infrastructure\Twig\Component\Img;
use Phil\MediaBundle\Infrastructure\Twig\Component\Picture;
use Phil\MediaBundle\Infrastructure\Twig\ImageCandidates;
use Phil\MediaBundle\Infrastructure\Twig\MediaExtension;
use Phil\MediaBundle\Infrastructure\Twig\ResponsiveImages;
use Phil\MediaBundle\Tests\Unit\Fixtures\Database;
use Phil\MediaBundle\Tests\Unit\Fixtures\Images;
use Phil\MediaBundle\Tests\Unit\Fixtures\MutableClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Link\LinkInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\WebLink\GenericLinkProvider;
use Symfony\Component\WebLink\Link;

#[CoversClass(ResponsiveImages::class)]
#[CoversClass(Img::class)]
#[CoversClass(Picture::class)]
final class PriorityAndPlaceholderTest extends TestCase
{
    private const string PREVIEW = 'data:image/webp;base64,UklGRg==';

    private RequestStack $requests;

    private MediaLibrary $library;

    private ResponsiveImages $images;

    protected function setUp(): void
    {
        $repository = new DoctrineMediaRepository(Database::entityManager());
        $clock = new MutableClock(new DateTimeImmutable('2026-11-06 12:00 UTC'));
        $this->library = new MediaLibrary($repository, new FilesystemStorage(Images::directory()), new GdImageProcessor(), $clock, [
            'card' => ImageFormat::of('card', 300, 200, widths: [150]),
        ]);
        $routes = new PhpFileLoader(new FileLocator(\dirname(__DIR__, 3).'/config'))->load('routes.php');
        $this->requests = new RequestStack();
        $this->images = new ResponsiveImages($this->library, new MediaExtension(new UrlGenerator($routes, new RequestContext()), new UrlSigner('secret'), $clock), $repository, $this->requests);
    }

    public function testThePlaceholderIsThePreviewAndTheFocalPointInPercents(): void
    {
        $media = Media::upload(FileInfo::of('a.png', 'image/png', 10, 40, 20), null, new DateTimeImmutable('2026-11-06 UTC'));

        self::assertNull($this->images->placeholder($media));
        $media->preview(self::PREVIEW);
        $media->focus(0.254, 0.996);
        self::assertSame(['src' => self::PREVIEW, 'position' => '25% 100%'], $this->images->placeholder($media));
        $media->focus(0.256, 0.004);
        self::assertSame(['src' => self::PREVIEW, 'position' => '26% 0%'], $this->images->placeholder($media));

        $img = new Img($this->images);
        $picture = new Picture($this->images);
        self::assertSame([true, true], [$img->placeholder, $picture->placeholder]);
        self::assertSame($this->images->placeholder($media), $img->preview($media));
        self::assertSame($this->images->placeholder($media), $picture->preview($media));
        $img->placeholder = false;
        $picture->placeholder = false;
        self::assertSame([null, null], [$img->preview($media), $picture->preview($media)]);
    }

    public function testPriorityLoadsEagerlyAndLeavesTheDecodingToTheBrowser(): void
    {
        foreach ([new Img($this->images), new Picture($this->images)] as $component) {
            self::assertFalse($component->priority);
            self::assertSame(['lazy', 'async'], [$component->loadingMode(), $component->decodingMode()]);
            $component->priority = true;
            self::assertSame(['eager', null], [$component->loadingMode(), $component->decodingMode()]);
            $component->loading = 'lazy';
            $component->decoding = 'sync';
            self::assertSame(['lazy', 'sync'], [$component->loadingMode(), $component->decodingMode()]);
            $component->decoding = 'auto';
            self::assertSame('auto', $component->decodingMode());
            $component->loading = 'soon';
            $component->decoding = 'later';
            self::assertSame(['eager', null], [$component->loadingMode(), $component->decodingMode()]);
            $component->priority = false;
            $component->loading = 'eager';
            $component->decoding = 'async';
            self::assertSame(['eager', 'async'], [$component->loadingMode(), $component->decodingMode()]);
            $component->loading = 'soon';
            $component->decoding = 'later';
            self::assertSame(['lazy', 'async'], [$component->loadingMode(), $component->decodingMode()]);
        }
    }

    public function testThePriorityImageIsPreloadedOnTheCurrentRequest(): void
    {
        $media = $this->library->upload(Images::file(Images::png(600, 400)), 'a.png');
        $img = new Img($this->images);
        $img->format = 'card';
        $img->sizes = '50vw';

        $image = $img->image($media);

        $img->preload($image);
        $this->requests->push($request = new Request());
        $img->preload($image);
        self::assertFalse($request->attributes->has('_links'));

        $img->priority = true;
        $img->preload($image);
        self::assertSame([['preload', $image->src, ['as' => 'image', 'fetchpriority' => 'high', 'imagesrcset' => (string) $image->srcset, 'imagesizes' => '50vw']]], $this->links($request));

        $picture = new Picture($this->images);
        $picture->media = $media;
        $picture->format = 'card';
        $picture->priority = true;

        $request->attributes->remove('_links');
        $picture->preload($media);
        $avif = $picture->sources($media)[0];
        self::assertSame([['preload', $avif->src, ['as' => 'image', 'fetchpriority' => 'high', 'imagesrcset' => (string) $avif->srcset, 'type' => 'image/avif']]], $this->links($request));

        $pdf = $this->library->upload(Images::file('%PDF-1.4'), 'a.pdf');
        $request->attributes->set('_links', new GenericLinkProvider([new Link(Link::REL_PRECONNECT, 'https://cdn.example')]));
        $picture->preload($pdf);
        self::assertSame([['preconnect', 'https://cdn.example', []], ['preload', $picture->fallback($pdf)->src, ['as' => 'image', 'fetchpriority' => 'high']]], $this->links($request));

        $picture->priority = false;
        $request->attributes->remove('_links');
        $picture->preload($media);
        self::assertFalse($request->attributes->has('_links'));
    }

    public function testAPreloadNeedsAnEvolvableProviderAndARequest(): void
    {
        $image = new ImageCandidates('/a.webp', null, null, null, 'image/webp');
        new ResponsiveImages($this->library, new MediaExtension(new UrlGenerator(new PhpFileLoader(new FileLocator(\dirname(__DIR__, 3).'/config'))->load('routes.php'), new RequestContext()), new UrlSigner('secret'), new MutableClock(new DateTimeImmutable())), new DoctrineMediaRepository(Database::entityManager()))->preload($image);
        $this->requests->push($request = new Request());
        $request->attributes->set('_links', 'not a provider');
        $this->images->preload($image, '100vw');
        self::assertSame('not a provider', $request->attributes->get('_links'));
        $request->attributes->remove('_links');
        $this->images->preload($image, '100vw');
        self::assertSame([['preload', '/a.webp', ['as' => 'image', 'fetchpriority' => 'high']]], $this->links($request));
    }

    /**
     * @return list<array{string, string, array<array-key, mixed>}>
     */
    private function links(Request $request): array
    {
        $provider = $request->attributes->get('_links');
        self::assertInstanceOf(GenericLinkProvider::class, $provider);
        $links = [];

        foreach ($provider->getLinks() as $link) {
            self::assertInstanceOf(LinkInterface::class, $link);
            $links[] = [implode(' ', $link->getRels()), $link->getHref(), $link->getAttributes()];
        }

        return $links;
    }
}
