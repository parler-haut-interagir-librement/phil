<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\BrowserSecurityBundle\Application\UserContentPolicy;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Application\UrlSigner;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Infrastructure\Doctrine\DoctrineMediaRepository;
use Phil\MediaBundle\Infrastructure\Http\MediaController;
use Phil\MediaBundle\Infrastructure\Image\GdImageProcessor;
use Phil\MediaBundle\Infrastructure\Storage\FilesystemStorage;
use Phil\MediaBundle\Infrastructure\Twig\MediaExtension;
use Phil\MediaBundle\Tests\Unit\Fixtures\Database;
use Phil\MediaBundle\Tests\Unit\Fixtures\Images;
use Phil\MediaBundle\Tests\Unit\Fixtures\MutableClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RequestContext;

#[CoversClass(MediaController::class)]
final class UserContentTest extends TestCase
{
    private DoctrineMediaRepository $repository;

    private MediaLibrary $library;

    private MediaExtension $urls;

    private UrlSigner $signer;

    private MutableClock $clock;

    protected function setUp(): void
    {
        if (!class_exists(UserContentPolicy::class)) {
            self::markTestSkipped('phil/browser-security-bundle is not installed.');
        }

        $this->repository = new DoctrineMediaRepository(Database::entityManager());
        $this->clock = new MutableClock(new DateTimeImmutable('2026-11-04 12:00 UTC'));
        $this->library = new MediaLibrary($this->repository, new FilesystemStorage(Images::directory()), new GdImageProcessor(), $this->clock, ['thumb' => ImageFormat::of('thumb', 10, 10)]);
        $routes = new PhpFileLoader(new FileLocator(\dirname(__DIR__, 3).'/config'))->load('routes.php');
        $routes->addPrefix('/media');

        $this->signer = new UrlSigner('secret');
        $this->urls = new MediaExtension(new UrlGenerator($routes, new RequestContext('/index.php')), $this->signer, $this->clock, 3600);
    }

    public function testTheOriginalCarriesThePolicyOfUserContent(): void
    {
        $media = $this->library->upload(Images::file(Images::png(40, 20)), 'une "fête".png');
        $response = $this->original($media, new UserContentPolicy());

        self::assertSame([
            'image/png',
            "default-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'; sandbox",
            'nosniff',
            'inline; filename=une-f-te-.png',
            'same-origin',
            'no-referrer',
            'none',
            'DENY',
            'immutable, max-age=3600, public',
        ], $this->headers($response));
        self::assertSame(Images::png(40, 20), $response->getContent());
    }

    public function testThePolicyDecidesWhatIsShownInline(): void
    {
        $pdf = $this->library->upload(Images::file('%PDF-1.4 a document'), 'rapport.pdf');
        $svg = $this->library->upload(Images::file('<svg xmlns="http://www.w3.org/2000/svg"/>'), 'logo.svg');

        $download = $this->original($pdf, new UserContentPolicy());
        self::assertSame(['application/octet-stream', 'attachment; filename=rapport.pdf'], [$download->headers->get('Content-Type'), $download->headers->get('Content-Disposition')]);
        $vector = $this->original($svg, new UserContentPolicy());
        self::assertSame(['application/octet-stream', 'attachment; filename=logo.svg'], [$vector->headers->get('Content-Type'), $vector->headers->get('Content-Disposition')]);

        $viewer = $this->original($pdf, new UserContentPolicy(['default-src' => ["'none'"]], [...UserContentPolicy::DEFAULT_INLINE_TYPES, 'application/pdf'], 'same-site'));
        self::assertSame(['application/pdf', "default-src 'none'", 'inline; filename=rapport.pdf', 'same-site', null], [$viewer->headers->get('Content-Type'), $viewer->headers->get('Content-Security-Policy'), $viewer->headers->get('Content-Disposition'), $viewer->headers->get('Cross-Origin-Resource-Policy'), $viewer->headers->get('X-Frame-Options')]);

        $image = $this->original($this->library->upload(Images::file(Images::png(4, 4)), 'a.png'), new UserContentPolicy(inlineTypes: ['text/plain']));
        self::assertSame(['application/octet-stream', 'attachment; filename=a.png'], [$image->headers->get('Content-Type'), $image->headers->get('Content-Disposition')]);
    }

    public function testAFormatCarriesThePolicyToo(): void
    {
        $media = $this->library->upload(Images::file(Images::png(40, 20)), 'a.png');
        $url = $this->urls->url($media, 'thumb', 'webp');
        $response = new MediaController($this->repository, $this->library, $this->signer, $this->clock, new UserContentPolicy())->format($this->request($url), $media->id, 1, 'thumb', 'webp');

        self::assertSame(['image/webp', 'inline', 'same-origin', 'no-referrer'], [$response->headers->get('Content-Type'), $response->headers->get('Content-Disposition'), $response->headers->get('Cross-Origin-Resource-Policy'), $response->headers->get('Referrer-Policy')]);
        self::assertStringStartsWith("default-src 'none';", (string) $response->headers->get('Content-Security-Policy'));
    }

    private function original(Media $media, UserContentPolicy $policy): Response
    {
        return new MediaController($this->repository, $this->library, $this->signer, $this->clock, $policy)->original($this->request($this->urls->url($media)), $media->id, 1);
    }

    /**
     * @return list<?string>
     */
    private function headers(Response $response): array
    {
        return array_map($response->headers->get(...), ['Content-Type', 'Content-Security-Policy', 'X-Content-Type-Options', 'Content-Disposition', 'Cross-Origin-Resource-Policy', 'Referrer-Policy', 'X-Permitted-Cross-Domain-Policies', 'X-Frame-Options', 'Cache-Control']);
    }

    private function request(string $url): Request
    {
        return Request::create('http://localhost'.$url, server: ['SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => '/index.php']);
    }
}
