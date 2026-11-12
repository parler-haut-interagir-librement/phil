<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Benchmark;

use Phil\UxKit\Tests\Functional\App\TestKernel;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Twig\Environment;
use Twig\TemplateWrapper;

/**
 * A gallery of 100 images rendered through TwigComponent, as a page of photos renders it on each request: each image
 * through the `Image` recipe, its origin checked, its srcset and sizes computed. In a grid, in a grid whose images
 * each open a lightbox, deferred with `content-visibility`, and with the AVIF and WebP sources of `Picture`. The
 * template is compiled once, in the cache of the kernel, before the measures: what is measured is the rendering.
 *
 * @BeforeMethods("setUp")
 *
 * @AfterMethods("tearDown")
 */
final class GalleryBench
{
    private const int IMAGES = 100;

    /** The markup rendered, kept so that rendering it is not dropped as unused. */
    public string $html = '';

    private ?TestKernel $kernel = null;

    private ?TemplateWrapper $template = null;

    /** @var list<array<string, mixed>> */
    private array $images = [];

    /**
     * @param array{gallery: string} $params
     */
    public function setUp(array $params): void
    {
        // The environment of the tests, whose container keeps Twig at hand, without debug, as in production.
        $this->kernel = new TestKernel('test', false);
        $this->kernel->boot();

        $container = $this->kernel->getContainer()->get('test.service_container');
        $twig = $container instanceof ContainerInterface ? $container->get('twig') : null;

        if (!$twig instanceof Environment) {
            throw new RuntimeException('Twig is missing from the kernel of the kit.');
        }

        $this->images = [];

        for ($i = 1; $i <= self::IMAGES; ++$i) {
            $this->images[] = [
                'src' => '/images/photo-'.$i.'-800.jpg',
                'srcset' => [400 => '/images/photo-'.$i.'-400.jpg', 800 => '/images/photo-'.$i.'-800.jpg', 1600 => '/images/photo-'.$i.'-1600.jpg'],
                'alt' => 'Photo '.$i.' of the evening',
                'width' => 800,
                'height' => 0 === $i % 3 ? 1200 : 600,
                'caption' => 0 === $i % 4 ? 'Moment '.$i : null,
            ];

            if ('picture' === $params['gallery']) {
                $this->images[$i - 1]['sources'] = [
                    ['type' => 'image/avif', 'srcset' => '/images/photo-'.$i.'-800.avif'],
                    ['type' => 'image/webp', 'srcset' => '/images/photo-'.$i.'-800.webp'],
                ];
            }
        }

        $this->template = $twig->createTemplate(match ($params['gallery']) {
            'lightbox' => '<twig:Gallery label="The evening" :lightbox="true" openLabel="Enlarge" sizes="(min-width: 48rem) 33vw, 100vw" :images="images" />',
            'deferred' => '<twig:Gallery label="The evening" :deferred="true" placeholder="neutral" sizes="(min-width: 48rem) 33vw, 100vw" :images="images" />',
            default => '<twig:Gallery label="The evening" sizes="(min-width: 48rem) 33vw, 100vw" :images="images" />',
        });
        // The first rendering compiles the components it reaches.
        $this->html = $this->template->render(['images' => $this->images]);
    }

    public function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    /**
     * @return iterable<string, array{gallery: string}>
     */
    public function provideGalleries(): iterable
    {
        yield 'grid' => ['gallery' => 'grid'];
        yield 'lightbox' => ['gallery' => 'lightbox'];
        yield 'deferred' => ['gallery' => 'deferred'];
        yield 'picture' => ['gallery' => 'picture'];
    }

    /**
     * @ParamProviders("provideGalleries")
     *
     * @Revs(20)
     *
     * @Iterations(5)
     */
    public function benchRenderAGalleryOfAHundredImages(): void
    {
        $this->html = $this->template?->render(['images' => $this->images]) ?? '';
    }
}
