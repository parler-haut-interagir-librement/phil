<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Browser;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\ProcessManager\WebServerManager;

/**
 * The table in a real Chrome, driven by the chromedriver of the system, on the page of the functional application
 * served by PHP's built-in server: the filters and the columns through Live Component, the focus given back after
 * each render, and the question of an action as a modal dialog that Escape dismisses. Run apart, like Infection:
 * `composer test:browser`.
 */
#[CoversNothing]
final class DataTableBrowserTest extends TestCase
{
    private const string HOST = '127.0.0.1';

    private const int PORT = 9181;

    /** The port of chromedriver, away from its default, which another suite may hold. */
    private const int DRIVER_PORT = 9616;

    private const int SECONDS = 10;

    private static ?WebServerManager $server = null;

    private static ?Client $browser = null;

    #[Override]
    public static function setUpBeforeClass(): void
    {
        if (!is_file(__DIR__.'/node_modules/@hotwired/stimulus/dist/stimulus.js')) {
            self::markTestSkipped('Stimulus is missing: run `npm ci --ignore-scripts` in '.__DIR__.'.');
        }

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

    #[Override]
    protected function setUp(): void
    {
        $this->browser()->request('GET', '/browser?size=10');
        $this->waitUntil('document.documentElement.dataset.started === "true"');
        $this->waitUntil('null !== window.application.getControllerForElementAndIdentifier(document.querySelector("section.phil-data-table"), "live")');
    }

    public function testAFilterTypedAndSubmittedNarrowsTheTableAndKeepsTheFocus(): void
    {
        $this->waitForStatus('60 résultats.');
        $search = $this->browser()->findElement(WebDriverBy::cssSelector('input[type=search]'));
        $search->click();
        $search->sendKeys('Book 1');
        // The model of a field follows its typing after Live Component's debounce, as it does for a person.
        usleep(400_000);
        $search->sendKeys(WebDriverKeys::ENTER);

        $this->waitForStatus('10 résultats.');
        $address = $this->read('location.search');
        self::assertIsString($address);
        self::assertStringContainsString('filters', $address);
        $this->waitUntil('document.activeElement.dataset.philFocus === "filter:q"');
    }

    public function testAColumnIsHiddenAndTheFocusStaysOnItsCheckbox(): void
    {
        $headings = $this->read('document.querySelectorAll("thead th").length');
        self::assertIsInt($headings);
        $this->browser()->findElement(WebDriverBy::cssSelector('.phil-data-table__columns summary'))->click();
        $this->browser()->findElement(WebDriverBy::cssSelector('input[data-phil-focus="column:pages"]'))->click();

        $this->waitUntil(\sprintf('document.querySelectorAll("thead th").length === %d', $headings - 1));
        $this->waitUntil('document.activeElement.dataset.philFocus === "column:pages"');
        self::assertFalse($this->read('document.querySelector(\'input[data-phil-focus="column:pages"]\').checked'));
        self::assertTrue($this->read('document.querySelector(".phil-data-table__columns").open'), 'the list of columns stays open');
    }

    public function testASortedHeadingKeepsTheFocus(): void
    {
        $this->browser()->findElement(WebDriverBy::cssSelector('button[data-phil-focus="sort:title"]'))->click();

        $this->waitUntil('document.querySelector("tbody th[scope=row]").textContent.trim().startsWith("Book 60")');
        $this->waitUntil('document.activeElement.dataset.philFocus === "sort:title"');
    }

    public function testTheQuestionOfAnActionIsAModalDialogThatEscapeDismisses(): void
    {
        $ask = 'a[data-phil-focus="ask:archive:1"]';
        $this->browser()->findElement(WebDriverBy::cssSelector($ask))->click();

        $this->waitUntil('document.querySelector("dialog.phil-data-table__dialog")?.matches(":modal") === true');
        $this->waitUntil('document.activeElement.dataset.phil__dataTableBundle__tableTarget === "dismiss" || document.activeElement.getAttribute("data-phil--data-table-bundle--table-target") === "dismiss"');

        $this->browser()->getKeyboard()->sendKeys(WebDriverKeys::ESCAPE);

        $this->waitUntil('document.querySelector("dialog.phil-data-table__dialog") === null');
        $this->waitUntil(\sprintf('document.activeElement.matches(%s)', json_encode($ask, \JSON_THROW_ON_ERROR)));
    }

    private function waitForStatus(string $text): void
    {
        $this->waitUntil(\sprintf('document.querySelector("section.phil-data-table [role=status]").textContent.trim() === %s', json_encode($text, \JSON_THROW_ON_ERROR)));
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
