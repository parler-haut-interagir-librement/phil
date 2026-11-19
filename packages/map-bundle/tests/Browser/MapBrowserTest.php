<?php

declare(strict_types=1);

namespace Phil\MapBundle\Tests\Browser;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\ProcessManager\WebServerManager;

/**
 * The maps in a real Chrome, driven by the chromedriver of the system, on the functional suite's application served
 * by PHP's built-in server: Leaflet drawn by Symfony UX Map with the bundle's controller beside it, the tiles asked of
 * the site's proxy, the areas and the booths in the classes of their states, the floor plan on Leaflet's flat
 * reference system, a PMTiles archive whose package is missing falling back on the proxied tiles, and the list under
 * each map. Run apart, like Infection: `composer test:browser`.
 */
#[CoversNothing]
final class MapBrowserTest extends TestCase
{
    private const string HOST = '127.0.0.1';

    private const int PORT = 9187;

    /** The port of chromedriver, away from its default and from the other suites'. */
    private const int DRIVER_PORT = 9622;

    private const int SECONDS = 10;

    private static ?WebServerManager $server = null;

    private static ?Client $browser = null;

    #[Override]
    public static function setUpBeforeClass(): void
    {
        if (!is_file(__DIR__.'/node_modules/leaflet/dist/leaflet-src.esm.js')) {
            self::markTestSkipped('Leaflet is missing: run `npm ci --ignore-scripts` in '.__DIR__.'.');
        }

        self::$server = new WebServerManager(__DIR__, self::HOST, self::PORT, __DIR__.'/router.php', env: ['TMPDIR' => sys_get_temp_dir()]);
        self::$server->start();

        self::$browser = Client::createChromeClient(null, null, ['port' => self::DRIVER_PORT], 'http://'.self::HOST.':'.self::PORT);
    }

    #[Override]
    public static function tearDownAfterClass(): void
    {
        self::$browser?->quit();
        self::$browser = null;
        self::$server?->quit();
        self::$server = null;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->browser()->request('GET', '/');
        $this->waitUntil('document.documentElement.dataset.started === "true"');
        $this->waitUntil('document.querySelectorAll(".leaflet-container").length === 4');
    }

    public function testTheMapsAreShownWithTheirLists(): void
    {
        self::assertSame(
            [true, true, true, true],
            $this->read('[...document.querySelectorAll(".phil-map-canvas")].map((m) => m.classList.contains("hasScript") && m.offsetHeight > 0)'),
        );
        self::assertSame(['Main hall <&>', 'Park'], $this->read('[...document.querySelectorAll("#venues .isItemTitle")].map((t) => t.textContent.trim())'));
        self::assertSame(['region', 'Our venues', 'venues-list'], $this->read('["role", "aria-label", "aria-describedby"].map((a) => document.querySelector("#venues .phil-map-canvas").getAttribute(a))'));
    }

    public function testTheTilesComeFromTheSitesProxy(): void
    {
        $this->waitUntil('document.querySelectorAll("#venues img.leaflet-tile").length > 0');

        self::assertTrue($this->read('[...document.querySelectorAll("#venues img.leaflet-tile")].every((tile) => new URL(tile.src).pathname.startsWith("/_map/tiles/") && new URL(tile.src).host === location.host)'));
        self::assertSame('no-referrer', $this->read('document.querySelector("#venues img.leaflet-tile").referrerPolicy'));
        self::assertSame(2, $this->read('document.querySelectorAll("#venues .leaflet-marker-icon").length'));
    }

    public function testTheAreasAndTheBoothsHaveTheClassesOfTheirStates(): void
    {
        self::assertSame(1, $this->read('document.querySelectorAll("#members path.phil-map-area").length'));
        self::assertSame(2, $this->read('document.querySelectorAll("#plan path.phil-map-booth.isUnknown").length'));
    }

    public function testTheFloorPlanIsDrawnOnTheFlatReferenceSystem(): void
    {
        $this->waitUntil('document.querySelector("#plan .phil-map-canvas").dataset.planDrawn === "image"');

        self::assertTrue($this->read('new URL(document.querySelector("#plan img.phil-map-plan-image").src).pathname === "/plans/hall.png"'));
        self::assertSame(0, $this->read('document.querySelectorAll("#plan img.leaflet-tile").length'), 'no geographic tiles under a plan');
    }

    public function testAnArchiveWithoutItsPackageFallsBackOnTheProxiedTiles(): void
    {
        $this->waitUntil('document.querySelector("#archive .phil-map-canvas").dataset.tiles === "fallback"');
        $this->waitUntil('document.querySelectorAll("#archive img.leaflet-tile").length > 0');

        self::assertTrue($this->read('[...document.querySelectorAll("#archive img.leaflet-tile")].every((tile) => new URL(tile.src).pathname.startsWith("/_map/tiles/"))'));
    }

    private function browser(): Client
    {
        return self::$browser ?? throw new RuntimeException('The browser is not started.');
    }

    private function read(string $expression): mixed
    {
        $driver = $this->browser()->getWebDriver();
        $this->assertInstanceOf(RemoteWebDriver::class, $driver);

        return $driver->executeScript('return '.$expression.';');
    }

    private function waitUntil(string $condition): void
    {
        $this->browser()->wait(self::SECONDS)->until(static fn (RemoteWebDriver $driver): bool => true === $driver->executeScript('return Boolean('.$condition.');'), 'Never true: '.$condition);
    }
}
