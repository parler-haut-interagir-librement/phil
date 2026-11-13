<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Tests\Browser;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Override;
use Phil\EmbedBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\ProcessManager\WebServerManager;

/**
 * The embed in a real Chrome, driven by the chromedriver of the system, across two origins served by PHP's built-in
 * server: a partner's page holds the code of an embed and a forged one; the site answers the embedded page and the
 * script. The frame shows once ready, at the height of its content, and follows it; the theme the partner asks for
 * reaches the frame; a message from elsewhere is ignored; the forged embed is refused framing and keeps its link.
 * Run apart, like Infection: `composer test:browser`.
 */
#[CoversNothing]
final class EmbedBrowserTest extends TestCase
{
    /** The port of chromedriver, away from its default and from the other suites'. */
    private const int DRIVER_PORT = 9623;

    private const int SECONDS = 10;

    private static ?WebServerManager $host = null;

    private static ?WebServerManager $site = null;

    private static ?Client $browser = null;

    #[Override]
    public static function setUpBeforeClass(): void
    {
        if (!is_file(__DIR__.'/node_modules/@hotwired/stimulus/dist/stimulus.js')) {
            throw new RuntimeException('Stimulus is missing: run `npm ci --ignore-scripts` in '.__DIR__.'.');
        }

        $env = ['TMPDIR' => sys_get_temp_dir()];
        self::$host = new WebServerManager(__DIR__, 'localhost', self::port(TestKernel::HOST), __DIR__.'/router.php', env: $env);
        self::$site = new WebServerManager(__DIR__, 'localhost', self::port(TestKernel::SITE), __DIR__.'/router.php', env: $env);
        self::$host->start();
        self::$site->start();

        self::$browser = Client::createChromeClient(null, null, ['port' => self::DRIVER_PORT], TestKernel::HOST);
    }

    #[Override]
    public static function tearDownAfterClass(): void
    {
        self::$browser?->quit();
        self::$browser = null;
        self::$host?->quit();
        self::$host = null;
        self::$site?->quit();
        self::$site = null;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->browser()->request('GET', TestKernel::HOST.'/');
        $this->waitUntil('document.querySelector("#good phil-embed")?.dataset.state === "ready"');
    }

    public function testTheFrameShowsOnceReadyAtTheHeightOfItsContent(): void
    {
        self::assertSame([false, true], $this->read('[document.querySelector("#good iframe").hidden, document.querySelector("#good a").hidden]'));
        $this->waitUntil('parseInt(document.querySelector("#good iframe").style.height, 10) >= 640');
        self::assertSame('Our calendar', $this->read('document.querySelector("#good iframe").title'));
        self::assertSame('no-referrer', $this->read('document.querySelector("#good iframe").referrerPolicy'));
    }

    public function testTheHeightFollowsTheContent(): void
    {
        $this->inFrame(static fn (RemoteWebDriver $driver): mixed => $driver->executeScript('document.getElementById("content").style.height = "900px";'));

        $this->waitUntil('parseInt(document.querySelector("#good iframe").style.height, 10) >= 900');

        $this->inFrame(static fn (RemoteWebDriver $driver): mixed => $driver->executeScript('document.getElementById("content").style.height = "200px";'));
        $this->waitUntil('parseInt(document.querySelector("#good iframe").style.height, 10) < 400');
        self::assertGreaterThanOrEqual(200, $this->read('parseInt(document.querySelector("#good iframe").style.height, 10)'), 'the frame shrinks with its content');
    }

    public function testTheThemeThePartnerAsksForReachesTheFrame(): void
    {
        self::assertSame('light', $this->inFrame(static fn (RemoteWebDriver $driver): mixed => $driver->executeScript('return document.documentElement.dataset.theme;')));

        $this->read('document.querySelector("#good phil-embed").setAttribute("theme", "dark") ?? true');
        $this->waitUntilInFrame('document.documentElement.dataset.theme === "dark"');

        $this->read('document.querySelector("#good phil-embed").setAttribute("theme", "neon") ?? true');
        usleep(300_000);
        self::assertSame('dark', $this->inFrame(static fn (RemoteWebDriver $driver): mixed => $driver->executeScript('return document.documentElement.dataset.theme;')), 'a theme the embed lacks is ignored');
    }

    public function testAMessageFromElsewhereIsIgnored(): void
    {
        $before = $this->read('document.querySelector("#good iframe").style.height');
        $this->read('window.postMessage({protocol: "phil-embed", version: 1, type: "resize", height: 7}, "*") ?? true');
        usleep(300_000);

        self::assertSame($before, $this->read('document.querySelector("#good iframe").style.height'));
    }

    public function testAForgedEmbedIsRefusedFramingAndKeepsItsLink(): void
    {
        $this->waitUntil('document.querySelector("#forged phil-embed").dataset.state === "fallback"');

        self::assertSame([0, false], $this->read('[document.querySelectorAll("#forged iframe").length, document.querySelector("#forged a").hidden]'));
    }

    private function inFrame(callable $work): mixed
    {
        $driver = $this->driver();
        $driver->switchTo()->frame($driver->findElement(WebDriverBy::cssSelector('#good iframe')));

        try {
            return $work($driver);
        } finally {
            $driver->switchTo()->defaultContent();
        }
    }

    private function waitUntilInFrame(string $condition): void
    {
        $this->browser()->wait(self::SECONDS)->until(fn (): bool => true === $this->inFrame(static fn (RemoteWebDriver $driver): mixed => $driver->executeScript('return Boolean('.$condition.');')), 'Never true in the frame: '.$condition);
    }

    private static function port(string $origin): int
    {
        return (int) substr($origin, (int) strrpos($origin, ':') + 1);
    }

    private function browser(): Client
    {
        return self::$browser ?? throw new RuntimeException('The browser is not started.');
    }

    private function driver(): RemoteWebDriver
    {
        $driver = $this->browser()->getWebDriver();
        $this->assertInstanceOf(RemoteWebDriver::class, $driver);

        return $driver;
    }

    private function read(string $expression): mixed
    {
        return $this->driver()->executeScript('return '.$expression.';');
    }

    private function waitUntil(string $condition): void
    {
        $this->browser()->wait(self::SECONDS)->until(static fn (RemoteWebDriver $driver): bool => true === $driver->executeScript('return Boolean('.$condition.');'), 'Never true: '.$condition);
    }
}
