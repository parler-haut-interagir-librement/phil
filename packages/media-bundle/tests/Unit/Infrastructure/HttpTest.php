<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Application\MediaSchema;
use Phil\MediaBundle\Application\UrlSigner;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Domain\Media\AiDisclosure;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Infrastructure\Doctrine\DoctrineMediaRepository;
use Phil\MediaBundle\Infrastructure\Http\MediaController;
use Phil\MediaBundle\Infrastructure\Image\GdImageProcessor;
use Phil\MediaBundle\Infrastructure\Storage\FilesystemStorage;
use Phil\MediaBundle\Infrastructure\Svg\SanitizingSvgPurifier;
use Phil\MediaBundle\Infrastructure\Twig\MediaExtension;
use Phil\MediaBundle\Tests\Unit\Fixtures\Database;
use Phil\MediaBundle\Tests\Unit\Fixtures\Images;
use Phil\MediaBundle\Tests\Unit\Fixtures\MutableClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(MediaController::class)]
#[CoversClass(MediaExtension::class)]
#[CoversClass(MediaSchema::class)]
final class HttpTest extends TestCase
{
    private DoctrineMediaRepository $repository;

    private MediaLibrary $library;

    private UrlGenerator $urls;

    private UrlSigner $signer;

    private MutableClock $clock;

    private FilesystemStorage $storage;

    protected function setUp(): void
    {
        $this->repository = new DoctrineMediaRepository(Database::entityManager());
        $this->clock = new MutableClock(new DateTimeImmutable('2026-11-04 12:00 UTC'));
        $this->storage = new FilesystemStorage(Images::directory());
        $this->library = new MediaLibrary($this->repository, $this->storage, new GdImageProcessor(), $this->clock, ['thumb' => ImageFormat::of('thumb', 100, 100, widths: [50])]);
        $routes = new PhpFileLoader(new FileLocator(\dirname(__DIR__, 3).'/config'))->load('routes.php');
        $routes->addPrefix('/media');

        $this->urls = new UrlGenerator($routes, new RequestContext('/index.php'));
        $this->signer = new UrlSigner('secret');
    }

    public function testASignedUrlServesTheFileAndItsFormats(): void
    {
        $media = $this->library->upload(Images::file(Images::png(400, 200)), 'une "fête".png');
        $url = new MediaExtension($this->urls, $this->signer, $this->clock, 3600)->url($media);
        $expires = 1_793_793_600 + 3600;

        self::assertSame(\sprintf('/index.php/media/%s/1/original?expires=%d&signature=%s', $media->id, $expires, $this->signer->signature(\sprintf('/media/%s/1/original', $media->id), $expires)), $url);

        $original = $this->controller()->original($this->request($url), $media->id, 1);
        self::assertSame([Images::png(400, 200), 'image/png', 'inline; filename="une-f-te-.png"'], [$original->getContent(), $original->headers->get('Content-Type'), $original->headers->get('Content-Disposition')]);
        self::assertSame(['nosniff', 'sandbox'], [$original->headers->get('X-Content-Type-Options'), $original->headers->get('Content-Security-Policy')]);
        self::assertSame('immutable, max-age=3600, public', $original->headers->get('Cache-Control'));

        $avif = new MediaExtension($this->urls, $this->signer, new NativeClock())->url($media, 'thumb', 'avif');
        self::assertSame(\sprintf('/index.php/media/%s/1/thumb.avif?signature=%s', $media->id, $this->signer->signature(\sprintf('/media/%s/1/thumb.avif', $media->id), null)), $avif);
        $thumb = $this->controller()->format($this->request($avif), $media->id, 1, 'thumb', 'avif');
        self::assertSame(['image/avif', 'immutable, max-age=31536000, public', 'nosniff', 'sandbox'], [$thumb->headers->get('Content-Type'), $thumb->headers->get('Cache-Control'), $thumb->headers->get('X-Content-Type-Options'), $thumb->headers->get('Content-Security-Policy')]);
        self::assertSame('ftypavif', substr((string) $thumb->getContent(), 4, 8));
        $webp = new MediaExtension($this->urls, $this->signer, new NativeClock())->url($media, 'thumb');
        self::assertStringStartsWith(\sprintf('/index.php/media/%s/1/thumb.webp?signature=', $media->id), $webp);
        self::assertSame('image/webp', $this->controller()->format($this->request($webp), $media->id, 1, 'thumb', 'webp')->headers->get('Content-Type'));
        $narrow = new MediaExtension($this->urls, $this->signer, new NativeClock())->url($media, 'thumb', 'png', 50);
        self::assertSame(\sprintf('/index.php/media/%s/1/thumb@50.png?signature=%s', $media->id, $this->signer->signature(\sprintf('/media/%s/1/thumb@50.png', $media->id), null)), $narrow);
        $size = getimagesizefromstring((string) $this->controller()->format($this->request($narrow), $media->id, 1, 'thumb', 'png', 50)->getContent());
        self::assertIsArray($size);
        self::assertSame([50, 50], [$size[0], $size[1]]);
        self::assertStringStartsWith(\sprintf('/index.php/media/%s/1/original.webp?signature=', $media->id), new MediaExtension($this->urls, $this->signer, new NativeClock())->url($media, 'original', 'webp'));

        $this->clock->now = new DateTimeImmutable('2026-11-04 12:59:30 UTC');
        self::assertSame('immutable, max-age=30, public', $this->controller()->original($this->request($url), $media->id, 1)->headers->get('Cache-Control'));
        $this->clock->now = new DateTimeImmutable('2026-11-04 13:00 UTC');
        $last = $this->controller()->original($this->request($url), $media->id, 1);
        self::assertSame([Response::HTTP_OK, 'immutable, max-age=0, public'], [$last->getStatusCode(), $last->headers->get('Cache-Control')]);
        self::assertEqualsWithDelta(time() + 63, (int) $this->query(new MediaExtension($this->urls, $this->signer, new NativeClock(), 60)->url($media))['expires'], 8);
    }

    public function testAUrlThatExpiresIsTheSameDuringAStep(): void
    {
        $media = $this->library->upload(Images::file(Images::png(40, 20)), 'a.png');
        $expires = fn (string $at, int $ttl): int => (int) $this->query(new MediaExtension($this->urls, $this->signer, new MutableClock(new DateTimeImmutable($at)), max(1, $ttl))->url($media))['expires'];

        // A tenth of an hour: from 12:00:01 to 12:06:00, every page hands out the URL that expires at 13:06.
        self::assertSame(1_793_797_200 + 360, $expires('2026-11-04 12:00:01 UTC', 3600));
        self::assertSame(1_793_797_200 + 360, $expires('2026-11-04 12:06:00 UTC', 3600));
        self::assertSame(1_793_797_200 + 720, $expires('2026-11-04 12:06:01 UTC', 3600));
        // An hour at most, and a second at least.
        self::assertSame(1_793_793_600 + 86_400 + 3600, $expires('2026-11-04 12:00:01 UTC', 86_400));
        self::assertSame(1_793_793_600 + 6, $expires('2026-11-04 12:00:01 UTC', 5));
        self::assertSame(1_793_793_600 + 7, $expires('2026-11-04 12:00:02 UTC', 5));
    }

    public function testAUrlThatExpiresWhileItIsServedIsNotKept(): void
    {
        $media = $this->library->upload(Images::file(Images::png(40, 20)), 'a.png');
        $url = new MediaExtension($this->urls, $this->signer, $this->clock, 3600)->url($media);
        // The URL is checked in its last second, and the response written in the next.
        $ticking = new class implements ClockInterface {
            private int $reads = 0;

            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable(0 === $this->reads++ ? '2026-11-04 13:00:00 UTC' : '2026-11-04 13:00:01 UTC');
            }
        };

        $response = new MediaController($this->repository, $this->library, $this->signer, $ticking)->original($this->request($url), $media->id, 1);

        self::assertSame([Response::HTTP_OK, 'immutable, max-age=0, public'], [$response->getStatusCode(), $response->headers->get('Cache-Control')]);
    }

    public function testOnlyTypesThatRunNoScriptAreShownInTheBrowser(): void
    {
        $extension = new MediaExtension($this->urls, $this->signer, $this->clock);
        $svg = $this->library->upload(Images::file('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), 'logo.svg');
        $html = $this->library->upload(Images::file('<!DOCTYPE html><html><body><script>alert(1)</script></body></html>'), 'page.html');
        $pdf = $this->library->upload(Images::file('%PDF-1.4 a document'), 'rapport.pdf');

        foreach ([[$svg, 'logo.svg'], [$html, 'page.html']] as [$media, $name]) {
            self::assertNotContains($media->file()->mimeType, MediaController::INLINE_TYPES, $name);
            $response = $this->controller()->original($this->request($extension->url($media)), $media->id, 1);
            self::assertSame(['application/octet-stream', \sprintf('attachment; filename="%s"', $name), 'nosniff', 'sandbox'], [$response->headers->get('Content-Type'), $response->headers->get('Content-Disposition'), $response->headers->get('X-Content-Type-Options'), $response->headers->get('Content-Security-Policy')], $name);
        }

        $document = $this->controller()->original($this->request($extension->url($pdf)), $pdf->id, 1);
        self::assertSame(['application/pdf', 'inline; filename="rapport.pdf"', 'nosniff', null], [$document->headers->get('Content-Type'), $document->headers->get('Content-Disposition'), $document->headers->get('X-Content-Type-Options'), $document->headers->get('Content-Security-Policy')]);
    }

    public function testAnSvgImageIsServedPurifiedAsAnImageWhenTheLibraryPurifies(): void
    {
        $extension = new MediaExtension($this->urls, $this->signer, $this->clock);
        // Kept before the purifier was turned on: purified as it is served.
        $svg = $this->library->upload(Images::file('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="1" height="1"/></svg>'), 'le "logo".svg');
        $purifying = new MediaLibrary($this->repository, $this->storage, new GdImageProcessor(), $this->clock, svg: new SanitizingSvgPurifier());
        $controller = new MediaController($this->repository, $purifying, $this->signer, $this->clock);

        $response = $controller->original($this->request($extension->url($svg)), $svg->id, 1);
        self::assertSame(['image/svg+xml', 'attachment; filename="le-logo-.svg"', 'nosniff', 'sandbox', 'immutable, max-age=31536000, public'], [$response->headers->get('Content-Type'), $response->headers->get('Content-Disposition'), $response->headers->get('X-Content-Type-Options'), $response->headers->get('Content-Security-Policy'), $response->headers->get('Cache-Control')]);
        self::assertSame('<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>', $response->getContent());

        $purifying->replace($svg, Images::file('<svg xmlns="http://www.w3.org/2000/svg"><circle r="1" onload="x()"/></svg>'), 'logo.svg');
        $next = $controller->original($this->request($extension->url($svg)), $svg->id, 2);
        self::assertSame('<svg xmlns="http://www.w3.org/2000/svg"><circle r="1"/></svg>', $next->getContent());

        $png = $purifying->upload(Images::file(Images::png(10, 10)), 'a.png');
        self::assertSame('image/png', $controller->original($this->request($extension->url($png)), $png->id, 1)->headers->get('Content-Type'));

        $bomb = $this->library->upload(Images::file('<!DOCTYPE svg [<!ENTITY a "b">]><svg xmlns="http://www.w3.org/2000/svg"/>'), 'bomb.svg');

        try {
            $controller->original($this->request($extension->url($bomb)), $bomb->id, 1);
            self::fail('An SVG image the purifier refuses is not served.');
        } catch (NotFoundHttpException $notFoundHttpException) {
            self::assertStringContainsString('The SVG image is refused', $notFoundHttpException->getMessage());
            self::assertInstanceOf(InvalidMedia::class, $notFoundHttpException->getPrevious());
        }
    }

    public function testEverythingElseIsNotFound(): void
    {
        $media = $this->library->upload(Images::file(Images::png(40, 20)), 'a.png');
        $url = new MediaExtension($this->urls, $this->signer, $this->clock, 3600)->url($media);
        $later = new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-11-04 13:00:01 UTC');
            }
        };
        $query = $this->query($url);
        $poster = new MediaExtension($this->urls, $this->signer, new NativeClock())->url($media, 'poster');

        foreach ([
            'expired' => [fn (): Response => new MediaController($this->repository, $this->library, $this->signer, $later)->original($this->request($url), $media->id, 1), 'This media URL is not signed, or expired.'],
            'not signed' => [fn (): Response => $this->controller()->original(Request::create(explode('?', $url)[0]), $media->id, 1), 'This media URL is not signed, or expired.'],
            'no expiry' => [fn (): Response => $this->controller()->original(Request::create(explode('?', $url)[0].'?signature='.$query['signature']), $media->id, 1), 'This media URL is not signed, or expired.'],
            'another secret' => [fn (): Response => new MediaController($this->repository, $this->library, new UrlSigner('other'), $this->clock)->original($this->request($url), $media->id, 1), 'This media URL is not signed, or expired.'],
            'old version' => [fn (): Response => $this->controller()->original($this->request($url), $media->id, 0), 'There is no such medium, or no such version.'],
            'unknown' => [fn (): Response => $this->controller()->original($this->request($url), '00000000-0000-0000-0000-000000000000', 1), 'There is no such medium, or no such version.'],
            'unknown format' => [fn (): Response => $this->controller()->format($this->request($poster), $media->id, 1, 'poster', 'webp'), 'There is no format "poster".'],
        ] as $case => [$call, $message]) {
            try {
                $call();
                self::fail($case);
            } catch (NotFoundHttpException $notFound) {
                self::assertSame($message, $notFound->getMessage(), $case);
            }
        }

        self::assertSame(Response::HTTP_OK, new MediaController($this->repository, $this->library, $this->signer, new NativeClock())->original($this->request(new MediaExtension($this->urls, $this->signer, new NativeClock())->url($media)), $media->id, 1)->getStatusCode());
        self::assertInstanceOf(Media::class, $this->repository->get($media->id));
    }

    private function controller(): MediaController
    {
        return new MediaController($this->repository, $this->library, $this->signer, $this->clock);
    }

    private function request(string $url): Request
    {
        return Request::create('http://localhost'.$url, server: ['SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => '/index.php']);
    }

    /**
     * @return array<string, string>
     */
    private function query(string $url): array
    {
        $query = [];

        foreach (explode('&', explode('?', $url)[1] ?? '') as $pair) {
            [$name, $value] = explode('=', $pair, 2) + [1 => ''];
            $query[$name] = urldecode($value);
        }

        return $query;
    }

    public function testAMediumIsSchemaOrgData(): void
    {
        $media = $this->library->upload(Images::file(Images::png(40, 20)), 'fête.png');
        $media->describe('La fête', 'Des gens qui dansent', 'Ada');
        $media->license('CC-BY-4.0', AiDisclosure::Generated);

        $secure = new UrlGenerator($this->routes(), new RequestContext('/index.php', host: 'example.com', scheme: 'https'));

        self::assertSame([
            '@context' => 'https://schema.org',
            '@type' => 'ImageObject',
            'contentUrl' => \sprintf('https://example.com/index.php/media/%s/1/original?signature=%s', $media->id, $this->signer->signature(\sprintf('/media/%s/1/original', $media->id), null)),
            'name' => 'La fête',
            'description' => 'Des gens qui dansent',
            'creditText' => 'Ada',
            'license' => 'https://spdx.org/licenses/CC-BY-4.0',
            'encodingFormat' => 'image/png',
            'width' => 40,
            'height' => 20,
            'dateModified' => '2026-11-04T12:00:00+00:00',
            'digitalSourceType' => 'https://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia',
        ], new MediaExtension($secure, $this->signer, new NativeClock())->schema($media));

        $media->license('all-rights-reserved', AiDisclosure::Assisted);
        $plain = new UrlGenerator($this->routes(), new RequestContext(host: 'example.com', httpPort: 8080));
        $schema = new MediaExtension($plain, $this->signer, new NativeClock())->schema($media);
        self::assertStringStartsWith('http://example.com:8080/media/', (string) $schema['contentUrl']);
        self::assertArrayNotHasKey('license', $schema);
        self::assertSame('https://cv.iptc.org/newscodes/digitalsourcetype/compositeWithTrainedAlgorithmicMedia', $schema['digitalSourceType']);
        self::assertStringStartsWith('https://example.com:8443/', (string) new MediaExtension(new UrlGenerator($this->routes(), new RequestContext(host: 'example.com', scheme: 'https', httpsPort: 8443)), $this->signer, new NativeClock())->schema($media)['contentUrl']);
        self::assertStringStartsWith('http://example.com/', (string) new MediaExtension(new UrlGenerator($this->routes(), new RequestContext(host: 'example.com')), $this->signer, new NativeClock())->schema($media)['contentUrl']);

        $media->license('CC0-1.0');
        $document = $this->library->upload(Images::file('%PDF-1.4'), 'rapport.pdf');
        self::assertSame(['@context' => 'https://schema.org', '@type' => 'MediaObject', 'name' => 'rapport.pdf', 'encodingFormat' => 'application/pdf', 'dateModified' => '2026-11-04T12:00:00+00:00'], array_diff_key(new MediaExtension($secure, $this->signer, new NativeClock())->schema($document), ['contentUrl' => true]));
        self::assertArrayNotHasKey('digitalSourceType', new MediaExtension($secure, $this->signer, new NativeClock())->schema($document));
    }

    private function routes(): RouteCollection
    {
        $routes = new PhpFileLoader(new FileLocator(\dirname(__DIR__, 3).'/config'))->load('routes.php');
        $routes->addPrefix('/media');

        return $routes;
    }
}
