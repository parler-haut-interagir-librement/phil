<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Browser;

use Composer\Autoload\ClassLoader;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\ProcessManager\WebServerManager;

/**
 * The filters controller in a headless Chrome, on the results page of the functional kernel served by PHP's own
 * server: a box ticked redraws the page in place and says the count, a link removing a filter is followed in place,
 * the focus comes back, and going back draws the previous filters. The page is never reloaded: a mark left on its
 * window survives.
 */
#[CoversNothing]
final class FiltersControllerTest extends TestCase
{
    private const string HOST = '127.0.0.1';

    private const int PORT = 9181;

    private const int SECONDS = 5;

    private const string STIMULUS = __DIR__.'/node_modules/@hotwired/stimulus/dist/stimulus.js';

    private const string STATUS = 'document.querySelector("[role=status]").textContent.trim()';

    private static ?WebServerManager $server = null;

    private static ?Client $browser = null;

    #[Override]
    public static function setUpBeforeClass(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        if (!self::hasChromedriver()) {
            self::markTestSkipped('The chromedriver of the system is missing: the browser test needs it.');
        }

        if (!is_file(self::STIMULUS)) {
            throw new RuntimeException('Stimulus is missing: run `npm ci --ignore-scripts` in '.__DIR__.'.');
        }

        $root = sys_get_temp_dir().'/phil-search-bundle-browser';
        $files = new Filesystem();
        $files->remove($root);
        $files->mirror(__DIR__.'/../../assets/src', $root);
        $files->copy(self::STIMULUS, $root.'/stimulus.js');
        $files->dumpFile($root.'/app.js', <<<'JS'
            import { Application } from '/stimulus.js';
            import Filters from '/filters_controller.js';

            Application.start().register('phil--search-bundle--filters', Filters);
            document.documentElement.dataset.started = 'true';
            JS);
        $files->dumpFile($root.'/index.php', <<<'PHP'
            <?php

            require getenv('PHIL_AUTOLOAD');

            $kernel = new Phil\SearchBundle\Tests\Functional\App\TestKernel('test', false);
            $request = Symfony\Component\HttpFoundation\Request::createFromGlobals();
            $response = $kernel->handle($request);
            $response->send();
            $kernel->terminate($request, $response);
            PHP);
        $autoload = \dirname((string) new ReflectionClass(ClassLoader::class)->getFileName(), 2).'/autoload.php';

        self::$server = new WebServerManager($root, self::HOST, self::PORT, '', '/games', ['TMPDIR' => sys_get_temp_dir(), 'PHIL_AUTOLOAD' => $autoload]);
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

    public function testATickedBoxRedrawsThePageInPlaceAndSaysTheCount(): void
    {
        $this->open('/games');

        self::assertTrue($this->read('document.querySelector("[data-phil--search-bundle--filters-target=submit]").hidden'));
        self::assertSame('5 résultats', $this->read(self::STATUS));

        $box = 'input[name="filter[genre][]"][value=jdr]';
        $this->click($box);
        $this->waitUntil(self::STATUS.' === "2 résultats"');

        self::assertTrue($this->read('window.mark === "kept"'), 'the page was not reloaded');
        $search = $this->read('location.search');
        self::assertIsString($search);
        self::assertStringContainsString('filter%5Bgenre%5D%5B%5D=jdr', $search);
        self::assertSame(['Un jeu de rôle', 'Un JDR de science-fiction'], $this->read('[...document.querySelectorAll("#results li")].map((item) => item.textContent)'));
        self::assertSame('Jeux (2)', $this->read('document.title'));
        self::assertTrue($this->read(\sprintf('document.querySelector(%s).checked', json_encode($box, \JSON_THROW_ON_ERROR))));
        self::assertTrue($this->read(\sprintf('document.activeElement.matches(%s)', json_encode($box, \JSON_THROW_ON_ERROR))), 'the focus came back to the box');
        self::assertTrue($this->read('document.querySelector("[data-phil--search-bundle--filters-target=submit]").hidden'));
        self::assertSame(1, $this->read('document.querySelectorAll("[role=status]").length'));

        $this->press(WebDriverKeys::SPACE);
        $this->waitUntil(self::STATUS.' === "5 résultats"');
        self::assertSame('', $this->read('location.search'));
    }

    public function testARemovedFilterIsFollowedInPlaceAndGoingBackDrawsItAgain(): void
    {
        $this->open('/games?filter[genre][]=jdr&filter[year][]=2026');
        self::assertSame('Un résultat', $this->read(self::STATUS));

        $this->browser()->findElement(WebDriverBy::cssSelector('.phil-search-filters__chips li:last-child a'))->click();
        $this->waitUntil(self::STATUS.' === "2 résultats"');

        self::assertTrue($this->read('window.mark === "kept"'));
        self::assertSame(1, $this->read('document.querySelectorAll(".phil-search-filters__chips li").length'));
        self::assertTrue($this->read('document.activeElement.id === "phil-search-filters"'), 'the link gone, the focus goes to the filters');

        $this->perform('history.back()');
        $this->waitUntil(self::STATUS.' === "Un résultat"');
        self::assertTrue($this->read('window.mark === "kept"'));
        self::assertSame(2, $this->read('document.querySelectorAll(".phil-search-filters__chips li").length'));

        $this->browser()->findElement(WebDriverBy::linkText('Retirer tous les filtres'))->click();
        $this->waitUntil(self::STATUS.' === "5 résultats"');
        self::assertSame(0, $this->read('document.querySelectorAll(".phil-search-filters__chips").length'));
        self::assertSame('', $this->read('location.search'));
    }

    private static function hasChromedriver(): bool
    {
        $path = getenv('PATH');

        return array_any(explode(\PATH_SEPARATOR, \is_string($path) ? $path : ''), static fn (string $directory): bool => '' !== $directory && is_executable($directory.'/chromedriver'));
    }

    private function open(string $path): void
    {
        $this->browser()->request('GET', $path);
        $this->waitUntil('document.documentElement.dataset.started === "true" && document.querySelector("#phil-search-filters").dataset.enhanced === "true"');
        $this->perform('window.mark = "kept"');
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

    private function perform(string $statement): void
    {
        $this->browser()->executeScript($statement);
    }

    private function click(string $selector): void
    {
        $this->browser()->findElement(WebDriverBy::cssSelector($selector))->click();
    }

    private function press(string $key): void
    {
        $this->browser()->getKeyboard()->sendKeys($key);
    }

    private function waitUntil(string $condition): void
    {
        $met = false;
        $this->browser()->wait(self::SECONDS)->until(function () use ($condition, &$met): bool {
            $met = true === $this->read('Boolean('.$condition.')');

            return $met;
        });

        self::assertTrue($met, $condition);
    }
}
