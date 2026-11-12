<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Benchmark;

use DateTimeImmutable;
use Generator;
use Phil\MediaBundle\Application\UrlSigner;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\ValueObject\FileInfo;
use Phil\MediaBundle\Infrastructure\Twig\MediaExtension;
use Phil\MediaBundle\Tests\Unit\Fixtures\MutableClock;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Generator\CompiledUrlGenerator;
use Symfony\Component\Routing\Generator\Dumper\CompiledUrlGeneratorDumper;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RequestContext;

/**
 * Galleries and product lists call `media_url()` once per image, often several times for a `srcset`, and each call
 * generates a route, computes an HMAC and builds a query string.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"media"})
 */
final class MediaUrlBench
{
    private readonly MediaExtension $forever;

    private readonly MediaExtension $expiring;

    private readonly UrlSigner $signer;

    /**
     * @var list<Media>
     */
    private array $media = [];

    /**
     * The bundle's routes compiled as the application's cache holds them, under the `/media` prefix.
     */
    public function __construct()
    {
        $routes = new PhpFileLoader(new FileLocator(\dirname(__DIR__, 2).'/config'))->load('routes.php');
        $routes->addPrefix('/media');

        $urls = new CompiledUrlGenerator(new CompiledUrlGeneratorDumper($routes)->getCompiledRoutes(), new RequestContext('/index.php'));
        $clock = new MutableClock(new DateTimeImmutable('2026-11-04 12:00 UTC'));

        $this->signer = new UrlSigner(str_repeat('s', 32));
        $this->forever = new MediaExtension($urls, $this->signer, $clock);
        $this->expiring = new MediaExtension($urls, $this->signer, $clock, 3600);
    }

    /**
     * @param array{images: int} $params
     */
    public function setUp(array $params): void
    {
        $at = new DateTimeImmutable('2026-11-04 12:00 UTC');

        for ($i = 0; $i < $params['images']; ++$i) {
            $this->media[] = Media::upload(FileInfo::of(\sprintf('photo-%d.jpg', $i), 'image/jpeg', 1_234_567, 4000, 3000), null, $at);
        }
    }

    /**
     * One original URL per image, expiring after an hour.
     *
     * @ParamProviders("galleries")
     */
    public function benchOriginalExpiring(): void
    {
        foreach ($this->media as $media) {
            $this->expiring->url($media);
        }
    }

    /**
     * A three-width `srcset` per image in AVIF, valid forever.
     *
     * @ParamProviders("galleries")
     */
    public function benchSrcsetForever(): void
    {
        foreach ($this->media as $media) {
            $this->forever->url($media, 'small', 'avif');
            $this->forever->url($media, 'medium', 'avif');
            $this->forever->url($media, 'large', 'avif');
        }
    }

    /**
     * The HMAC alone, once per image: the share of the signature in a URL.
     *
     * @ParamProviders("galleries")
     */
    public function benchSignatureOnly(): void
    {
        foreach ($this->media as $media) {
            $this->signer->signature('/media/'.$media->id.'/1/original', 1_793_797_200);
        }
    }

    /**
     * @return Generator<string, array{images: int}>
     */
    public function galleries(): Generator
    {
        yield '12 images' => ['images' => 12];
        yield '48 images' => ['images' => 48];
        yield '200 images' => ['images' => 200];
    }
}
