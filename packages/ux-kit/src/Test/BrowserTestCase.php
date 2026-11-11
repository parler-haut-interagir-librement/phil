<?php

declare(strict_types=1);

namespace Phil\UxKit\Test;

use Facebook\WebDriver\Interactions\WebDriverActions;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverHasInputDevices;
use Override;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\ProcessManager\WebServerManager;

/**
 * One headless Chrome on the page of the recipes of a kit, driven by the chromedriver of the system. Each test opens
 * the page afresh, in clear, in dark or right to left.
 *
 * Published for the browser suites of the kits built on the same controllers: a suite extends it and gives its
 * `RecipesPage`. It needs `phpunit/phpunit`, `symfony/panther` and `php-webdriver/webdriver`, which the kit requires
 * for development only.
 */
abstract class BrowserTestCase extends TestCase
{
    private const string HOST = '127.0.0.1';

    private const int PORT = 9180;

    private const int SECONDS = 5;

    private static ?WebServerManager $server = null;

    private static ?Client $browser = null;

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
     * @return iterable<string, array{string}>
     */
    public static function modes(): iterable
    {
        yield 'clear' => ['index.html'];
        yield 'dark' => ['dark.html'];
        yield 'right to left' => ['rtl.html'];
    }

    protected function open(string $page): void
    {
        $this->browser()->request('GET', '/'.$page);
        $this->waitUntil('document.documentElement.dataset.started === "true"');
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

    protected function isRightToLeft(string $page): bool
    {
        return 'rtl.html' === $page;
    }
}
