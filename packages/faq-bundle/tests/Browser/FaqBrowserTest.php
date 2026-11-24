<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Browser;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\WebDriverBy;
use Override;
use Phil\FaqBundle\Application\FaqEditor;
use Phil\FaqBundle\Domain\ValueObject\Locale;
use Phil\FaqBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\ProcessManager\WebServerManager;

/**
 * The page of questions in a headless Chrome, with the Stimulus controller running and with JavaScript off: the search
 * as one types, the question of the address, the votes, and axe-core on the whole component. A screen reader's own
 * announcement stays for a person to hear.
 *
 * It needs the chromedriver of the system and the packages of `package.json`, installed by `npm ci`; it is skipped
 * without them.
 */
#[CoversNothing]
final class FaqBrowserTest extends TestCase
{
    private const string HOST = '127.0.0.1';

    private const int PORT = 9193;

    /** A port of its own for chromedriver, so that the suite runs beside another one. */
    private const int DRIVER_PORT = 9593;

    private static ?WebServerManager $server = null;

    private ?Client $browser = null;

    #[Override]
    public static function setUpBeforeClass(): void
    {
        if (!is_file(__DIR__.'/node_modules/axe-core/axe.min.js') || !is_file(__DIR__.'/node_modules/@hotwired/stimulus/dist/stimulus.js')) {
            self::markTestSkipped('Run npm ci in tests/Browser to install Stimulus and axe-core.');
        }

        // The server's kernel is this one: its SQLite file is filled here.
        $kernel = new TestKernel('test', false);
        $kernel->boot();
        $kernel->resetDatabase();

        $editor = $kernel->getContainer()->get(FaqEditor::class);
        Assert::assertInstanceOf(FaqEditor::class, $editor);
        $en = Locale::fromString('en');
        $orders = $editor->addSection($en, 'Orders', 'orders');
        $editor->addQuestion($orders->id, $en, 'How do I pay?', 'By **card** or [transfer](https://example.org/transfer).');
        $editor->addQuestion($orders->id, $en, 'Where is my order?', 'At the *Café* of the post office.');

        $returns = $editor->addSection($en, 'Returns', 'returns');
        $editor->addQuestion($returns->id, $en, 'Can I return a game?', 'Within 30 days.');
        $kernel->shutdown();

        $public = __DIR__.'/public';
        self::$server = new WebServerManager($public, self::HOST, self::PORT, $public.'/index.php');
        self::$server->start();

        // The first request builds the container and the templates: the tests then wait for the browser only.
        file_get_contents('http://'.self::HOST.':'.self::PORT.'/faq/');
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

    public function testTypingFiltersTheQuestionsAndANoResultShowsTheContactLink(): void
    {
        $this->open('/faq/');
        $field = $this->browser()->findElement(WebDriverBy::cssSelector('#phil-faq-search-en'));

        $field->sendKeys('cafe');
        $this->waitUntil('document.querySelector("#how-do-i-pay").hidden && !document.querySelector("#where-is-my-order").hidden');
        self::assertTrue($this->read('document.querySelector("#faq-returns").hidden'));
        self::assertSame('One question matches “cafe”.', $this->read('document.querySelector(".phil-faq__status").textContent'));

        $field->clear();
        $field->sendKeys('gift cards');
        $this->waitUntil('!document.querySelector(".phil-faq__none").hidden');
        self::assertSame('/contact', $this->read('document.querySelector(".phil-faq__none a").getAttribute("href")'));
    }

    public function testTheQuestionOfTheAddressOpens(): void
    {
        $this->open('/faq/#where-is-my-order');

        $this->waitUntil('document.querySelector("#where-is-my-order").open');
        self::assertFalse($this->read('document.querySelector("#how-do-i-pay").open'));
    }

    public function testAVoteIsSentWithoutLeavingThePageAndRemembered(): void
    {
        $this->open('/faq/#can-i-return-a-game');
        $this->waitUntil('document.querySelector("#can-i-return-a-game").open');

        $this->browser()->findElement(WebDriverBy::cssSelector('#can-i-return-a-game button[value=yes]'))->click();
        $this->waitUntil('document.querySelector("#can-i-return-a-game .phil-faq__vote [role=status]") !== null');
        self::assertSame('Thank you for your answer.', $this->read('document.querySelector("#can-i-return-a-game .phil-faq__vote [role=status]").textContent'));
        self::assertTrue($this->read('location.href.endsWith("/faq/#can-i-return-a-game")'));

        $this->browser()->request('GET', '/faq/');
        $this->waitUntil('document.documentElement.dataset.started === "true" && document.querySelector("#can-i-return-a-game .phil-faq__vote [role=status]") !== null');
        self::assertNull($this->read('document.querySelector("#can-i-return-a-game button[value=yes]")'));
    }

    public function testTheComponentPassesAxe(): void
    {
        $this->open('/faq/');
        $this->browser()->executeScript((string) file_get_contents(__DIR__.'/node_modules/axe-core/axe.min.js'));
        $this->browser()->executeScript('document.querySelectorAll("details").forEach((one) => { one.open = true; })');

        self::assertSame([], $this->axe('.phil-faq'));
    }

    public function testWithoutJavaScriptTheServerSearchesAndCountsTheVote(): void
    {
        $this->open('/faq/', javascript: false);

        $this->browser()->findElement(WebDriverBy::cssSelector('#phil-faq-search-en'))->sendKeys('card');
        $this->browser()->findElement(WebDriverBy::cssSelector('form[role=search] button[type=submit]'))->click();
        $this->waitUntil('location.search === "?q=card"');
        self::assertSame(1, $this->read('document.querySelectorAll("details").length'));

        $this->browser()->findElement(WebDriverBy::cssSelector('#how-do-i-pay button[value=no]'))->click();
        $this->waitUntil('location.search.includes("voted=how-do-i-pay")');
        self::assertSame('Thank you for your answer.', $this->read('document.querySelector("#how-do-i-pay .phil-faq__vote [role=status]").textContent'));
    }

    private function open(string $path, bool $javascript = true): void
    {
        $options = new ChromeOptions();

        if (!$javascript) {
            $options->setExperimentalOption('prefs', ['profile.managed_default_content_settings.javascript' => 2]);
        }

        $this->browser = Client::createChromeClient(null, null, ['port' => self::DRIVER_PORT, 'capabilities' => [ChromeOptions::CAPABILITY => $options]], 'http://'.self::HOST.':'.self::PORT);
        $this->browser->request('GET', $path);

        if ($javascript) {
            $this->waitUntil('document.documentElement.dataset.started === "true"');
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

    private function waitUntil(string $condition): void
    {
        $this->browser()->wait(10)->until(fn (): bool => true === $this->read('Boolean('.$condition.')'));
        self::assertTrue($this->read('Boolean('.$condition.')'), $condition);
    }

    /**
     * The violations axe-core finds in a part of the page, each rule with the parts it found it in.
     *
     * @return list<string>
     */
    private function axe(string $selector): array
    {
        $this->browser()->manage()->timeouts()->setScriptTimeout(30);
        $violations = $this->browser()->executeAsyncScript(\sprintf(
            'const done = arguments[arguments.length - 1]; axe.run(document.querySelector(%s), {runOnly: {type: "tag", values: ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"]}}).then((result) => done(result.violations.map((violation) => violation.id + ": " + violation.nodes.map((node) => node.target.join(" ")).join(", "))), (error) => done(["axe: " + error]));',
            json_encode($selector, \JSON_THROW_ON_ERROR),
        ));
        self::assertIsArray($violations);

        return array_values(array_filter($violations, is_string(...)));
    }
}
