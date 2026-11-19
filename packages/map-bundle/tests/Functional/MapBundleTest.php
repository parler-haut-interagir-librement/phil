<?php

declare(strict_types=1);

namespace Phil\MapBundle\Tests\Functional;

use GdImage;
use Override;
use Phil\Geo\Coordinates;
use Phil\MapBundle\Application\Contract\BaseLayer;
use Phil\MapBundle\Application\Contract\BoothAvailability;
use Phil\MapBundle\Application\Maps;
use Phil\MapBundle\Application\Tiles\ProxiedTiles;
use Phil\MapBundle\Application\UnknownBoothAvailability;
use Phil\MapBundle\Infrastructure\Twig\MapExtension;
use Phil\MapBundle\PhilMapBundle;
use Phil\MapBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * The bundle in an application: a page of three maps rendered with Symfony UX Map, the bundle's controller and the
 * Item recipe of the kit; the tile proxy answering through its route; the tiler's command.
 */
#[CoversClass(PhilMapBundle::class)]
#[CoversClass(MapExtension::class)]
final class MapBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testAPageOfMaps(): void
    {
        $page = new Crawler((string) $this->get('/maps')->getContent());

        $venues = $page->filter('#venues');
        self::assertSame('phil-map isPlaces', $venues->attr('class'));
        $canvas = $venues->filter('.phil-map-canvas');
        self::assertSame('phil--map-bundle--map symfony--ux-leaflet-map--map', $canvas->attr('data-controller'));
        self::assertSame(['region', 'Our venues', 'venues-list'], [$canvas->attr('role'), $canvas->attr('aria-label'), $canvas->attr('aria-describedby')]);
        $options = json_decode((string) $canvas->attr('data-symfony--ux-leaflet-map--map-options-value'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($options);
        self::assertSame('/_map/tiles/{z}/{x}/{y}', $this->at($options, 'tileLayer', 'url'));
        self::assertSame('Lieux de la carte', $venues->filter('h2.phil-map-list-title')->text());
        self::assertSame(['Main hall <&>', 'Park'], $venues->filter('ul.isItemGroup li.isItem .isItemTitle')->each(static fn (Crawler $title): string => $title->text()));
        self::assertSame('/venues/hall', $venues->filter('.isItemTitle a')->attr('href'));
        self::assertSame(['Doors at 9'], $venues->filter('.isItemDescription')->each(static fn (Crawler $line): string => $line->text()));

        $members = $page->filter('#members');
        self::assertSame('phil-map-canvas isTall', $members->filter('[data-controller]')->attr('class'));
        self::assertSame('Secteurs de la carte', $members->filter('h3')->text());
        self::assertSame(['2 personnes dans ce secteur'], $members->filter('.isItemTitle')->each(static fn (Crawler $title): string => $title->text()));

        $plan = $page->filter('#plan');
        self::assertSame('Kiosques du plan', $plan->filter('h4')->text());
        self::assertSame(['Disponibilité inconnue', 'Disponibilité inconnue'], $plan->filter('.isItemDescription')->each(static fn (Crawler $line): string => $line->text()));
        $extra = json_decode((string) $plan->filter('[data-controller]')->attr('data-symfony--ux-leaflet-map--map-extra-value'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($extra);
        self::assertSame('/plans/hall.png', $this->at($extra, 'phil', 'plan', 'image'));
    }

    public function testAnIdIsGivenWhenNoneIsAndTheHeadingIsChecked(): void
    {
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $extension = $twig->getRuntime(MapExtension::class);
        $maps = self::getContainer()->get(Maps::class);
        self::assertInstanceOf(Maps::class, $maps);
        $view = $maps->places('Somewhere', [], Coordinates::of(1, 2));

        $html = new Crawler($extension->map($twig, $view, ['heading' => 'script', 'data-controller' => 'evil']));

        self::assertMatchesRegularExpression('/^phil-map-[0-9a-f]{8}$/', (string) $html->filter('figure')->attr('id'));
        self::assertCount(1, $html->filter('figcaption > h2'));
        self::assertSame('phil--map-bundle--map symfony--ux-leaflet-map--map', $html->filter('[data-controller]')->attr('data-controller'));
        self::assertSame('ui:Item', $extension->kit('Item'));
    }

    public function testTheServicesOfTheDefaults(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertInstanceOf(ProxiedTiles::class, $container->get(BaseLayer::class));
        self::assertInstanceOf(UnknownBoothAvailability::class, $container->get(BoothAvailability::class));
    }

    public function testTheTileProxyAnswersThroughItsRoute(): void
    {
        $response = $this->get('/_map/tiles/3/2/1');

        self::assertSame([200, 'PNG', 'image/png'], [$response->getStatusCode(), $response->getContent(), $response->headers->get('Content-Type')]);
        self::assertSame(404, $this->get('/_map/tiles/1/5/0')->getStatusCode());
        self::assertSame(404, $this->get('/_map/tiles/x/0/0')->getStatusCode());
    }

    public function testTheTilerCommand(): void
    {
        $kernel = self::bootKernel();
        $directory = sys_get_temp_dir().'/phil-map-command-'.bin2hex(random_bytes(4));
        mkdir($directory);
        $image = imagecreatetruecolor(20, 10);
        self::assertInstanceOf(GdImage::class, $image);
        imagepng($image, $directory.'/plan.png');
        $tester = new CommandTester(new Application($kernel)->find('phil:map:tile-image'));

        try {
            self::assertSame(0, $tester->execute(['image' => $directory.'/plan.png', 'directory' => $directory.'/tiles']));
            self::assertStringContainsString('1 tiles of 256 pixels written: the plan is 20 × 10 pixels, at its own size at zoom 0.', preg_replace('/\s+/', ' ', $tester->getDisplay()) ?? '');
            self::assertFileExists($directory.'/tiles/0/0/0.png');
            self::assertSame(1, $tester->execute(['image' => $directory.'/missing.png', 'directory' => $directory.'/tiles']));
            self::assertStringContainsString('is not an image the tiler can read.', preg_replace('/\s+/', ' ', $tester->getDisplay()) ?? '');
        } finally {
            new Filesystem()->remove($directory);
        }
    }

    private function get(string $path): Response
    {
        $kernel = self::bootKernel();

        return $kernel->handle(Request::create($path));
    }

    /**
     * The value under these keys of nested arrays.
     *
     * @param array<array-key, mixed> $data
     */
    private function at(array $data, string|int ...$keys): mixed
    {
        $value = $data;

        foreach ($keys as $key) {
            self::assertIsArray($value);
            self::assertArrayHasKey($key, $value);
            $value = $value[$key];
        }

        return $value;
    }
}
