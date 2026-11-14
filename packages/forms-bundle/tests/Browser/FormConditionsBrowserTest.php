<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Browser;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverSelect;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\ProcessManager\WebServerManager;

/**
 * The page of a form in a real Chrome, driven by the chromedriver of the system, with `phil/form-logic` installed as
 * its documentation says and the bundle's `_form.html.twig` included in the application's page: the fields asked
 * under a condition are hidden and shown as the answers change, in cascade, their controls disabled while hidden; a
 * field asked unless a choice is made is shown while nothing is chosen, as the server judges it.
 *
 * It needs the chromedriver of the system and Stimulus, installed by `npm ci --ignore-scripts` in this directory; it
 * is skipped without it. Run apart, like Infection: `composer test:browser`.
 */
#[CoversNothing]
final class FormConditionsBrowserTest extends TestCase
{
    private const string HOST = '127.0.0.1';

    private const int PORT = 9189;

    /** The port of chromedriver, away from its default and from the other suites'. */
    private const int DRIVER_PORT = 9624;

    private const int SECONDS = 10;

    /** What the scripts the tests run read the page with. */
    private const string HELPERS = 'const q = (s) => document.querySelector(s); const row = (field) => q(`[data-form-logic-field="${field}"]`); ';

    private static ?WebServerManager $server = null;

    private static ?Client $browser = null;

    #[Override]
    public static function setUpBeforeClass(): void
    {
        if (!is_file(__DIR__.'/node_modules/@hotwired/stimulus/dist/stimulus.js')) {
            self::markTestSkipped('Stimulus is missing: run `npm ci --ignore-scripts` in '.__DIR__.'.');
        }

        $public = __DIR__.'/public';
        self::$server = new WebServerManager($public, self::HOST, self::PORT, $public.'/index.php', '', ['TMPDIR' => sys_get_temp_dir()]);
        self::$server->start();

        // The first request builds the database, the container and the templates: the tests then wait for the browser only.
        file_get_contents('http://'.self::HOST.':'.self::PORT.'/forms/tournament');

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

    public function testThePageCarriesTheControllerOfPhilFormLogic(): void
    {
        $this->open();

        self::assertTrue($this->read('Boolean(window.application.getControllerForElementAndIdentifier(q("form[name=phil_form]"), "phil--form-logic--logic"))'));
        self::assertSame(['captain', 'armband', 'nickname'], $this->read('Array.from(document.querySelectorAll("[data-form-logic-field]"), (row) => row.dataset.formLogicField)'));
    }

    public function testAFieldIsHiddenThenShownAsTheAnswerChanges(): void
    {
        $this->open();

        self::assertSame(
            [true, true, true, true, false, false],
            $this->read('[row("captain").hidden, q("#phil_form_captain").disabled, row("armband").hidden, q("#phil_form_armband").disabled, row("nickname").hidden, q("#phil_form_nickname").disabled]'),
            'nothing chosen: no captain, but a nickname, since no team is not Green',
        );

        $this->choose('Blue');
        $this->waitUntil('!row("captain").hidden');
        self::assertSame([false, true, false], $this->read('[q("#phil_form_captain").disabled, row("armband").hidden, row("nickname").hidden]'));

        $this->browser()->findElement(WebDriverBy::id('phil_form_captain'))->click();
        $this->waitUntil('!row("armband").hidden');
        self::assertFalse($this->read('q("#phil_form_armband").disabled'));

        $this->choose('Green');
        $this->waitUntil('row("captain").hidden');
        self::assertSame(
            [true, true, true, true, true],
            $this->read('[q("#phil_form_captain").disabled, row("armband").hidden, q("#phil_form_armband").disabled, row("nickname").hidden, q("#phil_form_nickname").disabled]'),
            'the captain hidden, the armband that depends on it is hidden too',
        );

        $this->choose('Blue');
        $this->waitUntil('!row("armband").hidden');
        self::assertSame([true, false], $this->read('[q("#phil_form_captain").checked, row("nickname").hidden]'), 'shown again with its value');
    }

    private function open(): void
    {
        $this->browser()->request('GET', '/forms/tournament');
        $this->waitUntil('document.documentElement.dataset.started === "true" && window.changes.length > 0');
    }

    private function choose(string $team): void
    {
        new WebDriverSelect($this->browser()->findElement(WebDriverBy::id('phil_form_team')))->selectByValue($team);
    }

    private function browser(): Client
    {
        self::assertInstanceOf(Client::class, self::$browser);

        return self::$browser;
    }

    private function read(string $expression): mixed
    {
        return $this->browser()->executeScript(self::HELPERS.'return '.$expression);
    }

    private function waitUntil(string $condition): void
    {
        $this->browser()->wait(self::SECONDS, 100)->until(static fn (RemoteWebDriver $browser): bool => true === $browser->executeScript(self::HELPERS.'try { return Boolean('.$condition.'); } catch (e) { return false; }'), 'Never true: '.$condition);
    }
}
