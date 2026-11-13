<?php

declare(strict_types=1);

namespace Phil\UxEasyAdminBundle\Tests\Browser;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\ProcessManager\WebServerManager;

/**
 * A page of an EasyAdmin dashboard in a real Chrome, driven by the chromedriver of the system, served by PHP's built-in
 * server with EasyAdmin's own stylesheet: the colours the browser computes for the back-office are those of the PHIL UX
 * roles, in the light scheme and in the dark one, and the application's own styles still win over the theme. Run
 * apart, like Infection: `composer test:browser`.
 */
#[CoversNothing]
final class UxEasyAdminBrowserTest extends TestCase
{
    private const string HOST = '127.0.0.1';

    private const int PORT = 9185;

    /** The port of chromedriver, away from its default, which another suite may hold. */
    private const int DRIVER_PORT = 9620;

    private const int SECONDS = 10;

    /**
     * Gives an element of the page the colour of a variable, and answers the colour the browser computes for it.
     */
    private const string RESOLVE = '(variable, property) => { const probe = document.createElement("div"); probe.style.setProperty(property, "var(" + variable + ")"); document.querySelector(".main-content, body").append(probe); const value = getComputedStyle(probe).getPropertyValue(property); probe.remove(); return value; }';

    private static ?WebServerManager $server = null;

    private static ?Client $browser = null;

    #[Override]
    public static function setUpBeforeClass(): void
    {
        self::$server = new WebServerManager(__DIR__, self::HOST, self::PORT, __DIR__.'/router.php');
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

    public function testTheLightSchemeTakesTheRolesOfPhilUx(): void
    {
        $this->open('light');

        $surface = $this->resolve('--phil-color-surface', 'background-color');
        self::assertSame($surface, $this->read('getComputedStyle(document.body).backgroundColor'));
        self::assertSame($this->resolve('--phil-color-text', 'color'), $this->read('getComputedStyle(document.body).color'));
        self::assertSame($this->resolve('--phil-color-brand-fill-loud', 'color'), $this->resolve('--ea-primary', 'color'));
        self::assertSame($this->resolve('--phil-color-surface-raised', 'background-color'), $this->resolve('--sidebar-bg', 'background-color'));
        self::assertSame($this->resolve('--phil-color-danger-fill-quiet', 'background-color'), $this->resolve('--alert-danger-bg', 'background-color'));
        self::assertSame($this->resolve('--phil-color-success-on-quiet', 'color'), $this->resolve('--badge-success-color', 'color'));
        self::assertSame($this->resolve('--phil-color-border', 'border-top-color'), $this->resolve('--table-cell-border-color', 'border-top-color'));
        self::assertSame($this->resolve('--phil-radius-md', 'border-top-left-radius'), $this->resolve('--ea-radius', 'border-top-left-radius'));
    }

    public function testTheDarkSchemeTakesTheDarkRolesOfPhilUx(): void
    {
        $this->open('light');
        $light = $this->read('getComputedStyle(document.body).backgroundColor');

        $this->open('dark');

        self::assertTrue($this->read('document.body.classList.contains("ea-dark-scheme")'));
        $dark = $this->read('getComputedStyle(document.body).backgroundColor');
        self::assertSame($this->resolve('--phil-color-surface', 'background-color'), $dark);
        self::assertNotSame($light, $dark);
        self::assertSame($this->resolve('--phil-color-brand-fill-loud', 'color'), $this->resolve('--ea-primary', 'color'));
    }

    public function testTheApplicationsOwnStylesWinOverTheTheme(): void
    {
        $this->open('light');

        // The dashboard's stylesheet, outside any layer, sets a variable of EasyAdmin the theme leaves alone, and the
        // theme's layer loses to it whatever the order of the links.
        self::assertSame('1600px', $this->read('getComputedStyle(document.body).getPropertyValue("--body-max-width").trim()'));
        $this->browser()->executeScript('const style = document.createElement("style"); style.textContent = ":root, .ea-dark-scheme { --body-bg: rgb(1, 2, 3); }"; document.head.prepend(style);');
        $this->waitUntil('getComputedStyle(document.body).backgroundColor === "rgb(1, 2, 3)"');
    }

    /**
     * The dashboard's page in a colour scheme, kept by EasyAdmin's own script in the browser's storage.
     */
    private function open(string $scheme): void
    {
        $this->browser()->request('GET', '/admin');
        $this->browser()->executeScript(\sprintf('localStorage.setItem("ea/colorScheme", %s);', json_encode($scheme, \JSON_THROW_ON_ERROR)));
        $this->browser()->request('GET', '/admin');
        $this->waitUntil('document.readyState === "complete"');
        $this->waitUntil(\sprintf('document.body.classList.contains("ea-%s-scheme")', $scheme));
        $this->waitUntil('[...document.styleSheets].some((sheet) => sheet.href !== null && sheet.href.includes("/phil-ux-easyadmin/"))');
    }

    private function resolve(string $variable, string $property): string
    {
        $value = $this->browser()->executeScript(\sprintf('return (%s)(%s, %s);', self::RESOLVE, json_encode($variable, \JSON_THROW_ON_ERROR), json_encode($property, \JSON_THROW_ON_ERROR)));
        self::assertIsString($value);
        self::assertNotSame('', $value, $variable.' gives no '.$property.'.');

        return $value;
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
