<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Browser;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverElement;
use Facebook\WebDriver\WebDriverKeys;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\ProcessManager\WebServerManager;

/**
 * The mention controller in a real Chrome, driven by the chromedriver of the system, on a page served by PHP's
 * built-in server with a member search that answers as the block editor's: typing `@`, the list moved with the
 * keyboard, a handle chosen by Enter, Tab or a click, Escape, leaving the field, nobody found, a refused search, and
 * the roles, the states and the announcement a screen reader follows. Run apart, like Infection: `composer test:browser`.
 */
#[CoversNothing]
final class MentionBrowserTest extends TestCase
{
    private const string HOST = '127.0.0.1';

    private const int PORT = 9183;

    /** The port of chromedriver, away from its default and from the other suites'. */
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
        $this->browser()->request('GET', '/');
        $this->waitUntil('document.documentElement.dataset.started === "true"');
        $this->waitUntil('document.querySelector("#body").getAttribute("role") === "combobox"');
    }

    public function testTheFieldIsAComboboxThatControlsAListbox(): void
    {
        self::assertSame(
            ['combobox', 'list', 'false', 'listbox', true, 'status'],
            $this->read('[t.getAttribute("role"), t.getAttribute("aria-autocomplete"), t.getAttribute("aria-expanded"), document.getElementById(t.getAttribute("aria-controls")).getAttribute("role"), document.getElementById(t.getAttribute("aria-controls")).hidden, document.querySelector(".phil-mentions-status").getAttribute("role")]'),
        );
    }

    public function testTypingAnAtListsTheMembersAndAnnouncesHowMany(): void
    {
        $this->type('Hi @ad');

        $this->waitUntil('t.getAttribute("aria-expanded") === "true"');
        self::assertSame(['Ada Lovelace @ada', 'Adèle Exarchopoulos @adele'], $this->options());
        self::assertSame('2 members found', $this->read('document.querySelector(".phil-mentions-status").textContent'));
        self::assertSame(['true', 'false'], $this->read('[...document.querySelectorAll("[role=option]")].map((o) => o.getAttribute("aria-selected"))'));
        self::assertSame($this->read('document.querySelector("[role=option]").id'), $this->read('t.getAttribute("aria-activedescendant")'));
        self::assertTrue($this->read('document.activeElement === t'), 'the focus stays in the field');
    }

    public function testTheArrowsMoveInTheListAndEnterWritesTheHandle(): void
    {
        $this->type('Hi @ad');
        $this->waitForOptions(2);

        $this->field()->sendKeys(WebDriverKeys::ARROW_DOWN);
        self::assertSame(['false', 'true'], $this->read('[...document.querySelectorAll("[role=option]")].map((o) => o.getAttribute("aria-selected"))'));
        self::assertSame($this->read('document.querySelectorAll("[role=option]")[1].id'), $this->read('t.getAttribute("aria-activedescendant")'));
        $this->field()->sendKeys(WebDriverKeys::ARROW_DOWN);
        self::assertSame('true', $this->read('document.querySelectorAll("[role=option]")[0].getAttribute("aria-selected")'), 'the list wraps');
        $this->field()->sendKeys(WebDriverKeys::ARROW_UP);
        $this->field()->sendKeys(WebDriverKeys::ENTER);

        $this->waitUntil('t.getAttribute("aria-expanded") === "false"');
        self::assertSame(['Hi @adele ', 10, [['handle' => 'adele', 'reference' => 'user:4']], ''], $this->read('[t.value, t.selectionStart, window.chosen, document.querySelector(".phil-mentions-status").textContent]'));
        self::assertNull($this->read('t.getAttribute("aria-activedescendant")'));
    }

    public function testTabChoosesTheActiveMember(): void
    {
        $this->type('@b');
        $this->waitForOptions(1);
        $this->field()->sendKeys(WebDriverKeys::TAB);

        $this->waitUntil('t.value === "@bob "');
        self::assertTrue($this->read('document.activeElement === t'), 'Tab writes the handle rather than leaving the field');
    }

    public function testEscapeClosesTheListAndKeepsTheText(): void
    {
        $this->type('Hi @ad');
        $this->waitForOptions(2);
        $this->field()->sendKeys(WebDriverKeys::ESCAPE);

        $this->waitUntil('t.getAttribute("aria-expanded") === "false"');
        self::assertSame(['Hi @ad', true, 0], $this->read('[t.value, document.querySelector("[role=listbox]").hidden, document.querySelectorAll("[role=option]").length]'));
    }

    public function testAClickChoosesAMemberAndTheFocusStays(): void
    {
        $this->type('@ad');
        $this->waitForOptions(2);
        $this->browser()->findElement(WebDriverBy::cssSelector('[role=option]:first-child'))->click();

        $this->waitUntil('t.value === "@ada "');
        self::assertTrue($this->read('document.activeElement === t'));
    }

    public function testLeavingTheFieldClosesTheList(): void
    {
        $this->type('@ad');
        $this->waitForOptions(2);
        $this->browser()->findElement(WebDriverBy::id('elsewhere'))->click();

        $this->waitUntil('t.getAttribute("aria-expanded") === "false" && document.querySelector("[role=listbox]").hidden');
    }

    public function testNobodyFoundIsSaidInTheListAndAnnounced(): void
    {
        $this->type('@zzzz');

        $this->waitUntil('t.getAttribute("aria-expanded") === "true"');
        self::assertSame(['No member found', 'No member found', 0, null], $this->read('[document.querySelector(".phil-mentions-empty").textContent, document.querySelector(".phil-mentions-status").textContent, document.querySelectorAll("[role=option]").length, t.getAttribute("aria-activedescendant")]'));

        $this->field()->sendKeys(WebDriverKeys::ENTER);
        self::assertSame('@zzzz', $this->read('t.value.trimEnd()'), 'Enter on an empty list writes nothing');
    }

    public function testARefusedSearchListsNobodyAndFailsNothing(): void
    {
        $this->type('@boom');

        $this->waitUntil('document.querySelector(".phil-mentions-status").textContent === "No member found"');
        self::assertSame([], $this->read('window.chosen'));
    }

    public function testAnAddressIsNotAMention(): void
    {
        $this->type('ada@ex');
        usleep(300_000);

        self::assertSame(['false', true], $this->read('[t.getAttribute("aria-expanded"), document.querySelector("[role=listbox]").hidden]'));
    }

    private function type(string $text): void
    {
        $this->field()->click();
        $this->field()->sendKeys($text);
    }

    private function field(): WebDriverElement
    {
        return $this->browser()->findElement(WebDriverBy::id('body'));
    }

    private function waitForOptions(int $count): void
    {
        $this->waitUntil(\sprintf('document.querySelectorAll("[role=option]").length === %d', $count));
    }

    /**
     * @return list<string>
     */
    private function options(): array
    {
        $options = $this->read('[...document.querySelectorAll("[role=option]")].map((o) => o.textContent.replace(/\s+/g, " ").trim())');
        self::assertIsArray($options);

        return array_values(array_map(static fn (mixed $option): string => \is_string($option) ? $option : '', $options));
    }

    private function browser(): Client
    {
        self::assertInstanceOf(Client::class, self::$browser);

        return self::$browser;
    }

    private function read(string $expression): mixed
    {
        return $this->browser()->executeScript('const t = document.querySelector("#body"); return '.$expression);
    }

    private function waitUntil(string $condition): void
    {
        $this->browser()->wait(self::SECONDS, 100)->until(static fn (RemoteWebDriver $browser): bool => true === $browser->executeScript('const t = document.querySelector("#body"); try { return Boolean('.$condition.'); } catch (e) { return false; }'), 'Never true: '.$condition);
    }
}
