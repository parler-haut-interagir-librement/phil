<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Browser;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Cookie;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use Override;
use Phil\ReactionsBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\ProcessManager\WebServerManager;

/**
 * The checks of doc/testing.md in a headless Chrome, with the Live Component and the Stimulus controllers running: the
 * focus kept at the keyboard, the live region, two displays of one content, the picker, and the plain forms with
 * JavaScript off. A screen reader's own announcement stays for a person to hear.
 *
 * It needs the chromedriver of the system and a build of Stimulus, named by the environment variable
 * `PHIL_STIMULUS_JS`; it is skipped without it.
 */
#[CoversNothing]
final class ReactionsBrowserTest extends TestCase
{
    private const string HOST = '127.0.0.1';

    private const int PORT = 9181;

    /** A port of its own for chromedriver, so that the suite runs beside another one. */
    private const int DRIVER_PORT = 9581;

    private static ?WebServerManager $server = null;

    private ?Client $browser = null;

    #[Override]
    public static function setUpBeforeClass(): void
    {
        $stimulus = (string) getenv('PHIL_STIMULUS_JS');

        if ('' === $stimulus || !is_file($stimulus)) {
            self::markTestSkipped('PHIL_STIMULUS_JS names no build of Stimulus.');
        }

        // The server's kernel is this one: its SQLite file starts empty.
        $kernel = new TestKernel('test', false, 'ux-kit', 'ui');
        $kernel->boot();
        $kernel->resetDatabase();
        $kernel->shutdown();

        $public = __DIR__.'/public';
        self::$server = new WebServerManager($public, self::HOST, self::PORT, $public.'/index.php', '', ['PHIL_STIMULUS_JS' => $stimulus]);
        self::$server->start();

        // The first request builds the container and the templates: the tests then wait for the browser only.
        file_get_contents('http://'.self::HOST.':'.self::PORT.'/article');
    }

    #[Override]
    public static function tearDownAfterClass(): void
    {
        self::$server?->quit();
        self::$server = null;
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->browser?->quit();
        $this->browser = null;
    }

    public function testPressingAReactionAtTheKeyboardKeepsTheFocusAndSaysIt(): void
    {
        $this->open($this->member('keyboard'));
        $like = '.phil-reactions:nth-of-type(1) button[value=like]';

        $this->focus($like);
        $this->browser()->getKeyboard()->sendKeys(WebDriverKeys::ENTER);
        $this->waitUntil('document.querySelector("'.$like.'").getAttribute("aria-pressed") === "true"');

        $this->waitUntil('document.activeElement === document.querySelector("'.$like.'")');
        $text = $this->read('document.querySelector("'.$like.'").textContent');
        self::assertIsString($text);
        self::assertStringContainsString('1', $text);
        $this->waitUntil('document.querySelector(".phil-reactions__status").textContent.trim() !== ""');
        self::assertSame('polite', $this->read('document.querySelector(".phil-reactions__status").getAttribute("aria-live")'));
    }

    public function testAContentDrawnTwiceIsKeptInStep(): void
    {
        $this->open($this->member('twice'));

        $this->browser()->findElement(WebDriverBy::cssSelector('.phil-reactions:nth-of-type(1) button[value=like]'))->click();
        $this->waitUntil('document.querySelectorAll(".phil-reactions")[1].querySelector("button[value=like]").getAttribute("aria-pressed") === "true"');

        self::assertSame('false', $this->read('document.querySelectorAll(".phil-reactions")[2].querySelector("button[value=like]").getAttribute("aria-pressed")'), 'another content is left alone');
    }

    public function testThePickerOpensOnItsSearchAndMovesAtTheKeyboard(): void
    {
        $this->open($this->member('picker'));
        $picker = 'document.querySelector(".phil-reactions details")';

        $this->focus('.phil-reactions details summary');
        $this->browser()->getKeyboard()->sendKeys(WebDriverKeys::ENTER);
        $this->waitUntil($picker.'.open && document.activeElement.matches(".phil-reactions details input[type=search]")');

        self::assertFalse($this->read($picker.'.querySelector("input[type=search]").hidden'));
        $this->browser()->getKeyboard()->sendKeys(WebDriverKeys::ARROW_DOWN);
        $this->waitUntil('document.activeElement.matches(".phil-reactions details button[value=spark]")');
        $this->browser()->getKeyboard()->sendKeys(WebDriverKeys::END);
        $this->waitUntil('document.activeElement.matches(".phil-reactions details button[value=gnome]")');
        $this->browser()->getKeyboard()->sendKeys(WebDriverKeys::HOME);
        $this->waitUntil('document.activeElement.matches(".phil-reactions details button[value=spark]")');

        $this->browser()->getKeyboard()->sendKeys(WebDriverKeys::ARROW_UP);
        $this->waitUntil('document.activeElement.matches(".phil-reactions details input[type=search]")');
        $this->browser()->getKeyboard()->sendKeys('gno');
        $this->waitUntil($picker.'.querySelector("button[value=spark]").hidden');

        $this->browser()->getKeyboard()->sendKeys(WebDriverKeys::ESCAPE);
        $this->waitUntil('!'.$picker.'.open && document.activeElement.matches(".phil-reactions details summary")');
    }

    public function testWithoutJavaScriptEveryButtonGoesThroughTheForm(): void
    {
        $this->open($this->member('nojs'), false);

        self::assertNull($this->read('document.documentElement.dataset.started ?? null'));
        $this->browser()->findElement(WebDriverBy::cssSelector('.phil-reactions:nth-of-type(1) button[value=like]'))->click();
        $this->waitUntil('location.pathname === "/article" && document.querySelector(".phil-reactions button[value=like]").getAttribute("aria-pressed") === "true"');

        $this->browser()->findElement(WebDriverBy::cssSelector('.phil-reactions:nth-of-type(1) button[value=like]'))->click();
        $this->waitUntil('document.querySelector(".phil-reactions button[value=like]").getAttribute("aria-pressed") === "false"');
    }

    /**
     * A member of their own for each test, since the store is kept as long as the server runs.
     */
    private function member(string $test): string
    {
        return $test.'-'.bin2hex(random_bytes(4));
    }

    private function open(string $member, bool $javascript = true): void
    {
        $options = new ChromeOptions();

        if (!$javascript) {
            $options->setExperimentalOption('prefs', ['profile.managed_default_content_settings.javascript' => 2]);
        }

        $this->browser = Client::createChromeClient(null, null, ['port' => self::DRIVER_PORT, 'capabilities' => [ChromeOptions::CAPABILITY => $options]], 'http://'.self::HOST.':'.self::PORT);
        $this->browser->request('GET', '/assets/reactions.css');
        $this->browser->getWebDriver()->manage()->addCookie(new Cookie('member', $member));
        $this->browser->request('GET', '/article');

        if ($javascript) {
            $this->waitUntil('document.documentElement.dataset.started === "true" && document.querySelectorAll("[data-live-id], [data-controller~=live]").length > 0');
        }
    }

    private function browser(): Client
    {
        self::assertInstanceOf(Client::class, $this->browser);

        return $this->browser;
    }

    private function read(string $expression): mixed
    {
        return $this->browser()->executeScript('return '.$expression);
    }

    private function focus(string $selector): void
    {
        $this->browser()->executeScript(\sprintf('document.querySelector(%s).focus()', json_encode($selector, \JSON_THROW_ON_ERROR)));
    }

    private function waitUntil(string $condition): void
    {
        $this->browser()->wait(10)->until(fn (): bool => true === $this->read('Boolean('.$condition.')'));
        self::assertTrue($this->read('Boolean('.$condition.')'), $condition);
    }
}
