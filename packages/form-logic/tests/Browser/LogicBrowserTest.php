<?php

declare(strict_types=1);

namespace Phil\FormLogic\Tests\Browser;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\ProcessManager\WebServerManager;

/**
 * The logic controller in a real Chrome, driven by the chromedriver of the system, in an application installed as
 * doc/installation.md says — AssetMapper, StimulusBundle and `assets/controllers.json` — and served by PHP's built-in
 * server: fields shown and hidden in cascade as the form is filled, their controls disabled while hidden and their
 * values read back when shown again, a control the page disabled left so, a computed output, a hidden field the
 * browser does not send, and a rule outside the subset that leaves every field shown.
 *
 * It needs the chromedriver of the system and Stimulus, installed by `npm ci --ignore-scripts` in this directory; it
 * is skipped without it. Run apart, like Infection: `composer test:browser`.
 */
#[CoversNothing]
final class LogicBrowserTest extends TestCase
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
        if (!is_file(__DIR__.'/node_modules/@hotwired/stimulus/dist/stimulus.js')) {
            self::markTestSkipped('Stimulus is missing: run `npm ci --ignore-scripts` in '.__DIR__.'.');
        }

        $public = __DIR__.'/public';
        self::$server = new WebServerManager($public, self::HOST, self::PORT, $public.'/index.php', '', ['TMPDIR' => sys_get_temp_dir()]);
        self::$server->start();

        // The first request builds the container and the templates: the tests then wait for the browser only.
        file_get_contents('http://'.self::HOST.':'.self::PORT.'/');

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

    public function testAssetMapperServesTheControllerThatControllersJsonEnables(): void
    {
        $this->open('/');

        $imports = $this->read('JSON.parse(document.querySelector("script[type=importmap]").textContent).imports');
        self::assertIsArray($imports);
        self::assertArrayHasKey('/assets/@phil/form-logic/logic_controller.js', $imports);
        self::assertArrayHasKey('/assets/@phil/form-logic/logic.js', $imports);
        self::assertTrue($this->read('Boolean(window.application.getControllerForElementAndIdentifier(f, "phil--form-logic--logic"))'));
    }

    public function testAFieldWhoseConditionFailsIsHiddenAndItsControlsDisabled(): void
    {
        $this->open('/');

        self::assertSame(
            [true, true, true, true, true, '0'],
            $this->read('[q("#guest-field").hidden, q("#guest").disabled, q("#guest-name-field").hidden, q("#guest_name").disabled, q("#note-field").hidden, q("#seats").textContent]'),
        );
        self::assertSame([['guest', 'guest_name', 'note'], ['seats' => 0]], $this->read('[window.changes.at(-1).hidden, window.changes.at(-1).computed]'));
    }

    public function testFieldsAreShownAndHiddenInCascadeAndKeepTheirValues(): void
    {
        $this->open('/');

        $this->click('yes');
        $this->waitUntil('!q("#guest-field").hidden');
        self::assertSame([false, true, '1'], $this->read('[q("#guest").disabled, q("#guest-name-field").hidden, q("#seats").textContent]'));

        $this->click('guest');
        $this->waitUntil('!q("#guest-name-field").hidden');
        self::assertSame([false, '2'], $this->read('[q("#guest_name").disabled, q("#seats").textContent]'));
        $this->browser()->findElement(WebDriverBy::id('guest_name'))->sendKeys('Ada');

        $this->click('no');
        $this->waitUntil('q("#guest-field").hidden');
        self::assertSame([true, true, true, true, '0'], $this->read('[q("#guest").disabled, q("#guest-name-field").hidden, q("#guest_name").disabled, q("#note-field").hidden, q("#seats").textContent]'), 'the guest hidden, the fields that depend on it are hidden too');

        $this->click('yes');
        $this->waitUntil('!q("#guest-name-field").hidden');
        self::assertSame([true, 'Ada', false, '2'], $this->read('[q("#guest").checked, q("#guest_name").value, q("#guest_name").disabled, q("#seats").textContent]'));
    }

    public function testAControlThePageDisabledStaysDisabledWhenItsFieldIsShown(): void
    {
        $this->open('/');

        $this->click('yes');
        $this->click('guest');
        $this->waitUntil('!q("#note-field").hidden');

        self::assertSame([true, null], $this->read('[q("#note").disabled, q("#note").getAttribute("data-form-logic-disabled")]'));
    }

    public function testTheBrowserDoesNotSendAHiddenFieldAndTheServerJudgesAlike(): void
    {
        $this->open('/');

        $this->click('yes');
        $this->click('guest');
        $this->waitUntil('!q("#guest-name-field").hidden');
        $this->browser()->findElement(WebDriverBy::id('guest_name'))->sendKeys('Ada');
        $this->click('no');
        $this->waitUntil('q("#guest-field").hidden');
        $this->click('send');

        $this->browser()->wait(self::SECONDS, 100)->until(static fn (RemoteWebDriver $browser): bool => str_contains($browser->getCurrentURL(), '/sent'));
        $body = $this->read('document.body.innerText');
        self::assertIsString($body);
        $sent = json_decode($body, true, 8, \JSON_THROW_ON_ERROR);

        self::assertSame(['sent' => ['attending'], 'hidden' => ['guest', 'guest_name', 'note'], 'computed' => ['seats' => 0]], $sent);
    }

    public function testARuleOutsideTheSubsetLeavesEveryFieldShown(): void
    {
        $this->open('/?broken=1');

        self::assertSame(
            [false, false, false, false, false, ''],
            $this->read('[q("#guest-field").hidden, q("#guest").disabled, q("#guest-name-field").hidden, q("#guest_name").disabled, q("#note-field").hidden, q("#seats").textContent]'),
        );
        self::assertSame([[], []], $this->read('[window.changes.at(-1).hidden, window.changes.at(-1).computed]'));

        $this->click('guest');
        self::assertSame([false, ''], $this->read('[q("#guest-name-field").hidden, q("#seats").textContent]'));
    }

    private function open(string $path): void
    {
        $this->browser()->request('GET', $path);
        $this->waitUntil('document.documentElement.dataset.started === "true" && window.changes.length > 0');
    }

    private function click(string $id): void
    {
        $this->browser()->findElement(WebDriverBy::id($id))->click();
    }

    private function browser(): Client
    {
        self::assertInstanceOf(Client::class, self::$browser);

        return self::$browser;
    }

    private function read(string $expression): mixed
    {
        return $this->browser()->executeScript('const q = (s) => document.querySelector(s); const f = q("#registration"); return '.$expression);
    }

    private function waitUntil(string $condition): void
    {
        $this->browser()->wait(self::SECONDS, 100)->until(static fn (RemoteWebDriver $browser): bool => true === $browser->executeScript('const q = (s) => document.querySelector(s); try { return Boolean('.$condition.'); } catch (e) { return false; }'), 'Never true: '.$condition);
    }
}
