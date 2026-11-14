<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Browser;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use Override;
use Phil\PollBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\ProcessManager\WebServerManager;

/**
 * The ballot and the results in a real Chrome, driven by the chromedriver of the system, drawn by the PHIL UX kit and
 * by the Shadcn kit, on the pages of the functional application in its browser environments, served by PHP's
 * built-in server: each kind of ballot filled with the keyboard alone and posted, a blank ballot, a ballot refused and
 * its message announced, a form disabled for someone signed out, and the names a screen reader gives the groups, the
 * radio buttons and the bars, as Chrome computes them. Run apart, like Infection: `composer test:browser`.
 */
#[CoversNothing]
final class BallotBrowserTest extends TestCase
{
    private const string HOST = '127.0.0.1';

    private const int PORT = 9187;

    /** The port of chromedriver, away from its default and from those of the other browser suites. */
    private const int DRIVER_PORT = 9622;

    private const int SECONDS = 10;

    private static ?WebServerManager $server = null;

    private static ?Client $browser = null;

    #[Override]
    public static function setUpBeforeClass(): void
    {
        if (null === TestKernel::shadcnKit()) {
            self::markTestSkipped('The Symfony UX Toolkit is not installed.');
        }

        // New polls for each run: their files live in the caches of the kernel's browser environments.
        foreach ([TestKernel::BROWSER, TestKernel::BROWSER_SHADCN] as $environment) {
            $file = new TestKernel($environment, true)->getCacheDir().'/polls.sqlite';

            if (is_file($file)) {
                unlink($file);
            }
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function kits(): iterable
    {
        yield 'PHIL UX' => ['phil'];
        yield 'Shadcn' => ['shadcn'];
    }

    #[DataProvider('kits')]
    public function testASingleBallotIsFilledWithTheKeyboard(string $kit): void
    {
        $this->open($kit, 'single');

        $group = $this->element('[role="radiogroup"]');
        self::assertSame('radiogroup', $this->accessibleRole($group));
        self::assertSame('Which drink?', $this->accessibleName($group));
        self::assertSame('Coffee', $this->accessibleName($this->element('input[value="coffee"]')));
        self::assertFalse($this->read('document.querySelector("form").checkValidity()'), 'A choice is required.');

        $this->element('input[value="tea"]')->sendKeys(WebDriverKeys::ARROW_DOWN);
        $this->waitUntil('document.activeElement.value === "coffee" && document.activeElement.checked');
        $this->submitWithTheKeyboard();

        $this->waitUntil('document.querySelector("input[value=coffee]").checked');
        self::assertSame('Your ballot is recorded. You may change it while the poll is open.', $this->element('[role="status"]')->getText());
        self::assertSame('Change my ballot', $this->element('button[type="submit"]')->getText());
        self::assertSame('Coffee', $this->accessibleName($this->element('[role="progressbar"], progress')));
    }

    #[DataProvider('kits')]
    public function testABlankBallotIsCast(string $kit): void
    {
        $this->open($kit, 'multiple');

        self::assertTrue($this->read('document.querySelector("form").checkValidity()'), 'A blank ballot is accepted.');
        $this->element('button[type="submit"]')->sendKeys(WebDriverKeys::ENTER);

        $this->waitUntil('document.querySelector(".phil-poll-results__ballots").textContent.includes("1 of them blank.")');
    }

    #[DataProvider('kits')]
    public function testABallotRefusedIsAnnounced(string $kit): void
    {
        $this->open($kit, 'multiple', 'dee');

        foreach (['tea', 'coffee', 'cocoa'] as $drink) {
            $box = $this->element('input[value="'.$drink.'"]');
            self::assertSame('checkbox', $this->accessibleRole($box));
            $box->sendKeys(WebDriverKeys::SPACE);
        }

        $this->waitUntil('document.querySelectorAll("input[type=checkbox]:checked").length === 3');
        $this->submitWithTheKeyboard();

        $this->waitUntil('document.querySelector("[role=alert]") !== null');
        $alert = $this->element('[role="alert"]');
        self::assertSame('A ballot of this poll holds 1 to 2 choices, not 3.', trim($alert->getText()));
        self::assertStringEndsWith('-error', (string) $alert->getAttribute('id'));
        self::assertTrue($this->read('document.querySelector("fieldset").getAttribute("aria-describedby").split(" ").includes(document.querySelector("[role=alert]").id)'));
        self::assertSame('true', $this->element('fieldset')->getAttribute('aria-invalid'));
    }

    #[DataProvider('kits')]
    public function testARankedBallotIsFilledWithTheKeyboard(string $kit): void
    {
        $this->open($kit, 'ranked');

        $select = $this->element('select[name="ranks[cocoa]"]');
        self::assertSame('combobox', $this->accessibleRole($select));
        self::assertSame('Cocoa', $this->accessibleName($select));
        // A closed select is chosen by typing the start of an option, as a keyboard user does.
        $select->sendKeys('Rank 1');
        $this->element('select[name="ranks[tea]"]')->sendKeys('Rank 2');
        $this->waitUntil('document.querySelector("select[name=\'ranks[cocoa]\']").value === "1" && document.querySelector("select[name=\'ranks[tea]\']").value === "2"');
        $this->submitWithTheKeyboard();

        $this->waitUntil('document.querySelector("select[name=\'ranks[cocoa]\']").value === "1"');
        self::assertSame('2', $this->read('document.querySelector("select[name=\'ranks[tea]\']").value'));
        self::assertSame('Cocoa', $this->element('[data-winner] span')->getText());
    }

    #[DataProvider('kits')]
    public function testAScaleIsMarkedWithTheKeyboard(string $kit): void
    {
        $this->open($kit, 'scale');

        self::assertSame('How was the evening?', $this->accessibleName($this->element('[role="radiogroup"]')));
        self::assertSame('Great', $this->accessibleName($this->element('input[value="5"]')));
        $this->element('input[value="1"]')->sendKeys(WebDriverKeys::ARROW_DOWN.WebDriverKeys::ARROW_DOWN.WebDriverKeys::ARROW_DOWN);
        $this->waitUntil('document.activeElement.value === "4" && document.activeElement.checked');
        $this->submitWithTheKeyboard();

        $this->waitUntil('document.querySelector(".phil-poll-results__mean") !== null');
        self::assertSame('Mean mark: 4.00, from 1 mark.', $this->element('.phil-poll-results__mean')->getText());
        self::assertSame(['1', '2', '3', '4', '5'], $this->read('[...document.querySelectorAll(".phil-poll-results__line > span:first-child")].map(label => label.textContent.trim()).map(label => label === "Poor" ? "1" : label === "Great" ? "5" : label)'));
    }

    #[DataProvider('kits')]
    public function testANetPromoterScoreIsShownWithItsGroups(string $kit): void
    {
        $this->open($kit, 'nps');

        // The arrows wrap around a group of radio buttons: up from the lowest mark is the highest.
        $this->element('input[value="0"]')->sendKeys(WebDriverKeys::ARROW_UP);
        $this->waitUntil('document.activeElement.value === "10" && document.activeElement.checked');
        $this->submitWithTheKeyboard();

        $this->waitUntil('document.querySelector(".phil-poll-results__nps") !== null');
        self::assertSame(['Net Promoter Score', '100', 'Promoters (9–10)', '1', 'Passives (7–8)', '0', 'Detractors (0–6)', '0'], $this->read('[...document.querySelectorAll(".phil-poll-results__nps > *")].map(item => item.textContent.trim())'));
    }

    #[DataProvider('kits')]
    public function testTheFormIsDisabledWhenVotingIsNotAllowed(string $kit): void
    {
        $this->open($kit, 'single', '');

        self::assertFalse($this->element('input[value="tea"]')->isEnabled());
        self::assertTrue($this->read('document.querySelector("fieldset").disabled'));
        self::assertSame(0, $this->read('document.querySelectorAll("button").length'));

        $this->open($kit, 'closed');
        self::assertFalse($this->element('input[value="tea"]')->isEnabled());
        self::assertSame('This poll is not open.', $this->element('.phil-poll-ballot__status')->getText());
        self::assertSame('Coffee', $this->element('[data-winner] span')->getText());
        self::assertSame('Winner', $this->element('[data-winner] strong')->getText());
    }

    private function open(string $kit, string $kind, string $as = 'bob'): void
    {
        $this->browser()->request('GET', '/'.$kit.'/'.$kind.'?as='.$as);
        $this->waitUntil('document.querySelector("form.phil-poll-ballot") !== null');
    }

    /**
     * Tabs from the control in focus to the button of the form, and presses Enter there.
     */
    private function submitWithTheKeyboard(): void
    {
        for ($tab = 0; $tab < 5 && 'submit' !== $this->read('document.activeElement.type'); ++$tab) {
            $this->driver()->getKeyboard()->sendKeys(WebDriverKeys::TAB);
        }

        $this->waitUntil('document.activeElement.type === "submit"');
        $this->driver()->getKeyboard()->sendKeys(WebDriverKeys::ENTER);
        $this->waitUntil('document.readyState === "complete" && document.querySelector("form.phil-poll-ballot") !== null');
    }

    private function element(string $selector): RemoteWebElement
    {
        $element = $this->driver()->findElement(WebDriverBy::cssSelector($selector));
        self::assertInstanceOf(RemoteWebElement::class, $element);

        return $element;
    }

    /**
     * The accessible name Chrome computes for the element.
     */
    private function accessibleName(RemoteWebElement $element): string
    {
        $name = $this->driver()->executeCustomCommand('/session/:sessionId/element/'.$element->getID().'/computedlabel');
        self::assertIsString($name);

        return $name;
    }

    /**
     * The role Chrome computes for the element.
     */
    private function accessibleRole(RemoteWebElement $element): string
    {
        $role = $this->driver()->executeCustomCommand('/session/:sessionId/element/'.$element->getID().'/computedrole');
        self::assertIsString($role);

        return $role;
    }

    private function browser(): Client
    {
        self::assertInstanceOf(Client::class, self::$browser);

        return self::$browser;
    }

    private function driver(): RemoteWebDriver
    {
        $driver = $this->browser()->getWebDriver();
        self::assertInstanceOf(RemoteWebDriver::class, $driver);

        return $driver;
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
