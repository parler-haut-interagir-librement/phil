<?php

declare(strict_types=1);

namespace Phil\UxKit\Test;

use Facebook\WebDriver\Interactions\WebDriverActions;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverHasInputDevices;
use Override;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\ProcessManager\WebServerManager;

/**
 * One headless Chrome on the page of the recipes of a kit, driven by the chromedriver of the system. Each test opens
 * the page afresh, in clear, in dark or right to left, and each of them under a strict Content Security Policy too: on
 * such a page, whatever the browser refused fails the test, checked before the next page and at the end of the test.
 *
 * Published for the browser suites of the kits built on the same controllers: a suite extends it and gives its
 * `RecipesPage`. It needs `phpunit/phpunit`, `symfony/panther` and `php-webdriver/webdriver`, which the kit requires
 * for development only.
 *
 * A suite that gives the directory of its references, `screenshots()`, compares parts of the page with them through
 * `assertLooksLike()`, within the tolerance of `Screenshots`: a reference missing, or every reference when
 * `PHIL_UX_UPDATE_SCREENSHOTS` is `1`, is written from the run, and the test marked incomplete so that it is seen.
 */
abstract class BrowserTestCase extends TestCase
{
    private const string HOST = '127.0.0.1';

    private const int PORT = 9180;

    private const int SECONDS = 5;

    private static ?WebServerManager $server = null;

    private static ?Client $browser = null;

    /**
     * The page the test opened last.
     */
    private ?string $page = null;

    #[Override]
    public static function setUpBeforeClass(): void
    {
        $root = static::recipesPage()->write();

        self::$server = new WebServerManager($root, self::HOST, self::PORT);
        self::$server->start();

        self::$browser = Client::createChromeClient(null, null, [], 'http://'.self::HOST.':'.self::PORT);
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
     * The page of the kit under test.
     */
    abstract protected static function recipesPage(): RecipesPage;

    /**
     * The directory of the reference screenshots of the suite, none by default.
     */
    protected static function screenshots(): ?string
    {
        return null;
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function modes(): iterable
    {
        yield 'clear' => ['index.html'];
        yield 'dark' => ['dark.html'];
        yield 'right to left' => ['rtl.html'];
    }

    /**
     * The same modes under the strict policy of `RecipesPage`.
     *
     * @return iterable<string, array{string}>
     */
    public static function strictModes(): iterable
    {
        yield 'clear under a strict policy' => ['strict.html'];
        yield 'dark under a strict policy' => ['strict-dark.html'];
        yield 'right to left under a strict policy' => ['strict-rtl.html'];
    }

    protected function open(string $page): void
    {
        $this->assertThePolicyHeld();
        $this->browser()->request('GET', '/'.$page);
        $this->page = $page;
        $this->waitUntil('document.documentElement.dataset.started === "true"');
    }

    #[Override]
    protected function assertPostConditions(): void
    {
        $this->assertThePolicyHeld();
    }

    /**
     * On a page under the strict policy, that the browser refused nothing: no inline style or script, no resource of
     * another origin.
     */
    protected function assertThePolicyHeld(): void
    {
        if (null === $this->page || !$this->isStrict($this->page)) {
            return;
        }

        self::assertSame([], $this->read('window.violations ?? ["the watcher of the policy did not run"]'), 'Refused under the strict policy of '.$this->page);
    }

    protected function browser(): Client
    {
        self::assertInstanceOf(Client::class, self::$browser);

        return self::$browser;
    }

    protected function read(string $expression): mixed
    {
        return $this->browser()->executeScript('return '.$expression);
    }

    protected function perform(string $statement): void
    {
        $this->browser()->executeScript($statement);
    }

    /**
     * The real keyboard, on what has the focus.
     */
    protected function press(string ...$keys): void
    {
        foreach ($keys as $key) {
            $this->browser()->getKeyboard()->sendKeys($key);
        }
    }

    protected function focus(string $selector): void
    {
        $this->perform(\sprintf('document.querySelector(%s).focus()', json_encode($selector, \JSON_THROW_ON_ERROR)));
    }

    protected function click(string $selector): void
    {
        $this->browser()->findElement(WebDriverBy::cssSelector($selector))->click();
    }

    /**
     * A real click just outside the start edge of an element, half way down: on the backdrop of a modal dialog.
     */
    protected function clickBeside(string $selector): void
    {
        $width = $this->read(\sprintf('Math.ceil(document.querySelector(%s).getBoundingClientRect().width)', json_encode($selector, \JSON_THROW_ON_ERROR)));
        self::assertIsInt($width);
        $driver = $this->browser()->getWebDriver();
        self::assertInstanceOf(WebDriverHasInputDevices::class, $driver);

        new WebDriverActions($driver)->moveToElement($this->browser()->findElement(WebDriverBy::cssSelector($selector)), -intdiv($width, 2) - 8, 0)->click()->perform();
    }

    protected function waitUntil(string $condition): void
    {
        $expression = 'Boolean('.$condition.')';
        $met = false;

        // The reading that met the condition is the one asserted: a passing state, as a word of a headline, may be
        // gone by a second reading.
        $this->browser()->wait(self::SECONDS)->until(function () use ($expression, &$met): bool {
            $met = true === $this->read($expression);

            return $met;
        });

        self::assertTrue($met, $condition);
    }

    protected function waitForFocus(string $selector): void
    {
        $this->waitUntil(\sprintf('document.activeElement.matches(%s)', json_encode($selector, \JSON_THROW_ON_ERROR)));
    }

    /**
     * That a part of the page looks like its reference, `<name>.png` in the directory of `screenshots()`, within the
     * tolerance of `Screenshots`. On a mismatch, the screenshot is written beside the temporary directory for a look.
     */
    protected function assertLooksLike(string $selector, string $name, Screenshots $screenshots = new Screenshots()): void
    {
        $directory = static::screenshots();
        self::assertNotNull($directory, 'The suite gives the directory of its reference screenshots in screenshots().');

        $element = $this->browser()->findElement(WebDriverBy::cssSelector($selector));
        self::assertInstanceOf(RemoteWebElement::class, $element);
        $found = \sprintf('document.querySelector(%s)', json_encode($selector, \JSON_THROW_ON_ERROR));
        // In view, so that its lazy images load, then once they have and the fonts with them.
        $this->perform($found.'.scrollIntoView({block: "center"})');
        $this->waitUntil(\sprintf('document.fonts.status === "loaded" && [%1$s, ...%1$s.querySelectorAll("img")].filter((image) => "IMG" === image.nodeName).every((image) => image.complete && image.naturalWidth > 0)', $found));
        $width = $this->read('Math.round('.$found.'.getBoundingClientRect().width)');
        self::assertIsInt($width);
        $png = $element->takeElementScreenshot();
        $reference = $directory.'/'.$name.'.png';

        if ('1' === getenv(Screenshots::UPDATE) || !is_file($reference)) {
            file_put_contents($reference, $screenshots->reference($png, $width));
            self::markTestIncomplete('Reference screenshot written: '.$reference);
        }

        $difference = $screenshots->difference($png, $width, (string) file_get_contents($reference));
        $seen = sys_get_temp_dir().'/phil-ux-screenshots/'.$name.'.png';

        if (!$screenshots->tolerates($difference)) {
            @mkdir(\dirname($seen), 0o777, true);
            file_put_contents($seen, $screenshots->reference($png, $width));
        }

        self::assertTrue($screenshots->tolerates($difference), \sprintf('%s looks unlike %s: %.1f %% of its pixels changed; seen in %s.', $selector, $reference, $difference * 100, $seen));
    }

    protected function isRightToLeft(string $page): bool
    {
        return 'rtl.html' === $page || 'strict-rtl.html' === $page;
    }

    protected function isStrict(string $page): bool
    {
        return str_starts_with($page, 'strict');
    }
}
