<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Tests\Browser;

use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use Override;
use Phil\CheckinBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\ProcessManager\WebServerManager;

/**
 * The desk in a real Chrome, driven by the chromedriver of the system, on the page of the functional application in
 * its browser environment, served by PHP's built-in server: a scan online, then the network cut by Chrome's DevTools,
 * scans decided from the manifest the controller kept in IndexedDB, and the journal synchronised once the network is
 * back. Run apart, like Infection: `composer test:browser`.
 */
#[CoversNothing]
final class CheckinDeskBrowserTest extends TestCase
{
    private const string HOST = '127.0.0.1';

    private const int PORT = 9183;

    /** The port of chromedriver, away from its default and from those of the other browser suites. */
    private const int DRIVER_PORT = 9618;

    private const int SECONDS = 10;

    private static ?WebServerManager $server = null;

    private static ?Client $browser = null;

    #[Override]
    public static function setUpBeforeClass(): void
    {
        if (!is_file(__DIR__.'/node_modules/@hotwired/stimulus/dist/stimulus.js')) {
            self::markTestSkipped('Stimulus is missing: run `npm ci --ignore-scripts` in '.__DIR__.'.');
        }

        // A new ledger for each run: the file lives in the cache of the kernel's browser environment.
        $ledger = new TestKernel(TestKernel::BROWSER, true)->getCacheDir().'/checkin.sqlite';

        if (is_file($ledger)) {
            unlink($ledger);
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

    public function testADeskKeepsCheckingInOfflineAndSynchronisesOnceBack(): void
    {
        $this->browser()->request('GET', '/');
        $this->waitUntil('document.documentElement.dataset.started === "true"');
        $this->waitUntil('document.querySelector("form").dataset.store === "indexeddb"');
        $this->waitUntil('document.querySelector("form").dataset.pending === "0"');
        $this->waitUntil('document.querySelector("p").textContent === "Online"');

        $this->scan('CODE-1');
        $this->waitForResult('Admitted — Élise');

        $this->network(false);
        $this->waitUntil('document.querySelector("p").textContent === "Offline"');

        $this->scan('CODE-2');
        $this->waitForResult('Admitted — (decided offline)');
        $this->scan('CODE-1');
        // A manifest carries no label unless the application asks for them: offline, the name is not shown.
        $this->waitForResult('Already admitted — (decided offline)');
        $this->scan('NOPE');
        $this->waitForResult('Unknown code — (decided offline)');
        $this->waitUntil('document.querySelector("p").textContent === "Offline · 2 scans to send"');
        $this->waitUntil('document.activeElement === document.querySelector("input")');

        $this->network(true);
        $this->waitUntil('document.querySelector("form").dataset.pending === "0"');
        $this->waitUntil('document.querySelector("p").textContent === "Online"');

        // The server now holds the entry made offline: the holder is inside.
        $this->scan('CODE-2');
        $this->waitForResult('Already admitted');
        self::assertFalse($this->read('document.querySelector("output").textContent.includes("decided offline")'));
    }

    private function scan(string $code): void
    {
        $field = $this->browser()->findElement(WebDriverBy::cssSelector('input'));
        $field->sendKeys($code);
        $field->sendKeys(WebDriverKeys::ENTER);
    }

    /**
     * Cuts the network or gives it back, as DevTools does: `navigator.onLine` follows, and the page hears the event.
     */
    private function network(bool $online): void
    {
        $driver = $this->browser()->getWebDriver();
        self::assertInstanceOf(RemoteWebDriver::class, $driver);
        $devTools = new ChromeDevToolsDriver($driver);
        $devTools->execute('Network.enable');
        $devTools->execute('Network.emulateNetworkConditions', ['offline' => !$online, 'latency' => 0, 'downloadThroughput' => -1, 'uploadThroughput' => -1]);
    }

    private function waitForResult(string $text): void
    {
        $this->waitUntil(\sprintf('document.querySelector("output").textContent.startsWith(%s)', json_encode($text, \JSON_THROW_ON_ERROR)));
    }

    private function browser(): Client
    {
        self::assertInstanceOf(Client::class, self::$browser);

        return self::$browser;
    }

    private function read(string $expression): mixed
    {
        return $this->browser()->executeScript('return '.$expression);
    }

    private function waitUntil(string $condition): void
    {
        $this->browser()->wait(self::SECONDS, 100)->until(static fn (RemoteWebDriver $browser): bool => true === $browser->executeScript('try { return Boolean('.$condition.'); } catch (e) { return false; }'), 'Never true: '.$condition);
    }
}
