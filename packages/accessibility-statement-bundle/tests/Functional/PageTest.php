<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Tests\Functional;

use Override;
use Phil\AccessibilityStatement\Statement;
use Phil\AccessibilityStatement\StatementReader;
use Phil\AccessibilityStatementBundle\Infrastructure\Http\StatementController;
use Phil\AccessibilityStatementBundle\Infrastructure\Twig\KitComponents;
use Phil\AccessibilityStatementBundle\PhilAccessibilityStatementBundle;
use Phil\AccessibilityStatementBundle\Tests\Functional\App\TestKernel;
use Phil\AccessibilityStatementBundle\Tests\Unit\Fixture\MemoryStatements;
use Phil\AccessibilityStatementBundle\Tests\Unit\Fixture\Statements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;

/**
 * The page of the statement rendered by the application's Twig, its parts drawn by the recipes of the kit installed as
 * the Symfony UX Toolkit would, in a subdirectory of the components or at their root.
 */
#[CoversClass(StatementController::class)]
#[CoversClass(KitComponents::class)]
final class PageTest extends KernelTestCase
{
    private static string $subdirectory = 'ui';

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true, 'ux-kit', self::$subdirectory);
    }

    #[Override]
    protected function setUp(): void
    {
        self::$subdirectory = 'ui';
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function subdirectories(): iterable
    {
        yield 'PHIL UX in a subdirectory' => ['ui'];
        yield 'PHIL UX' => [''];
    }

    #[DataProvider('subdirectories')]
    public function testThePartsAreDrawnByTheRecipesOfTheKit(string $subdirectory): void
    {
        self::$subdirectory = $subdirectory;
        $current = StatementReader::fromArray(Statements::complete());
        $html = (string) $this->controller([$current, StatementReader::fromArray(Statements::minimal(2))], feedbackForm: 'accessibility-feedback')->current()->getContent();

        self::assertMatchesRegularExpression('~<li>\s*<article data-slot="card" data-size="sm" class="isCard isSmall">~', $html);
        self::assertStringContainsString('<h4 class="phil-accessibility-statement__criterion">1.4.3 Contrast (Minimum) (level AA)</h4>', $html);
        self::assertStringContainsString('<div data-slot="card-content" class="isCardBody">', $html);
        self::assertStringContainsString('<table data-slot="table" class="isTable">', $html);
        self::assertStringContainsString('<th data-slot="table-head" class="isTableHead" scope="col">Version</th>', $html);
        self::assertMatchesRegularExpression('~<span data-slot="badge" data-variant="secondary" class="isBadge isSecondary">\s*in force\s*</span>~', $html);
        self::assertStringContainsString('<time datetime="2026-11-01">2026-11-01</time>', $html);
        self::assertStringContainsString('class="isButton isOutline" href="/forms/accessibility-feedback">Write to us</a>', $html, 'the form of phil/forms-bundle before the route');
        self::assertSame(1, substr_count($html, 'Write to us'));
    }

    public function testTheStatementInForceIsShownWithEveryPart(): void
    {
        $previous = StatementReader::fromArray(Statements::minimal(2));
        $current = StatementReader::fromArray(Statements::complete());
        $response = $this->controller([$current, $previous], feedbackRoute: 'contact')->current();
        $html = (string) $response->getContent();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('max-age=3600, public', $response->headers->get('Cache-Control'));
        self::assertStringContainsString('<title>Accessibility statement</title>', $html);
        self::assertStringContainsString('<p>Example Org is committed to making the site example.com accessible to everyone', $html);
        self::assertStringContainsString('Measured against WCAG 2.2 AA, the site example.com partially conforms', $html);
        self::assertStringContainsString('<a href="https://www.w3.org/TR/WCAG22/" hreflang="en">Read WCAG 2.2</a>', $html);
        self::assertStringContainsString('<h3>Success criteria not met</h3>', $html);
        self::assertMatchesRegularExpression('~criterion">'.preg_quote('1.4.3 Contrast (Minimum) (level AA)', '~').'</h4>~', $html);
        self::assertStringContainsString('<p>Grey text on the event pages.</p>', $html);
        self::assertStringContainsString('<p>Found 12 times by the last evaluation.</p>', $html);
        self::assertStringContainsString('<p>Found once by the last evaluation.</p>', $html);
        self::assertStringContainsString('<p>Meanwhile: Ask us for the text.</p>', $html);
        self::assertStringContainsString('<p>To be met by 2027-03-31.</p>', $html);
        self::assertStringContainsString('<h3>Disproportionate burden</h3>', $html);
        self::assertStringContainsString('<h3>Content outside the scope of this statement</h3>', $html);
        self::assertLessThan(strpos($html, 'Disproportionate burden'), strpos($html, 'Success criteria not met'));
        self::assertStringContainsString('<li>Call us and we read the page to you.</li>', $html);
        self::assertStringContainsString('<a data-slot="button" data-variant="outline" data-size="default" class="isButton isOutline" href="/contact">Write to us</a>', $html);
        self::assertStringContainsString('<li><a href="/feedback">Give feedback</a></li>', $html);
        self::assertStringContainsString('<a href="mailto:accessibility@example.com">accessibility@example.com</a>', $html);
        self::assertStringContainsString('<a href="tel:+15145550100">+1 514 555 0100</a>', $html);
        self::assertStringContainsString('<address>1 Main Street<br />', $html);
        self::assertStringContainsString('<p>We answer within 10 days.</p>', $html);
        self::assertStringContainsString('<a href="https://example.org/complaints">The Accessibility Commissioner</a>', $html);
        self::assertStringContainsString('This statement, version 3, was published on 2026-11-01.', $html);
        self::assertStringContainsString('The content was last evaluated on 2026-10-30, by an automated check.', $html);
        self::assertStringContainsString('2 pages were evaluated.', $html);
        self::assertStringContainsString('<a href="/audit.html">Read the evaluation report</a>', $html);
        self::assertStringContainsString('<a href="/accessibility/v3" aria-current="page">Version 3, 2026-11-01</a>', $html);
        self::assertMatchesRegularExpression('~Version 3, 2026-11-01</a>\s*<span data-slot="badge"[^>]*>\s*in force~', $html);
        self::assertDoesNotMatchRegularExpression('~Version 2, 2026-11-01</a>\s*<span data-slot="badge"~', $html);
        self::assertSame(1, substr_count($html, 'in force'));
        self::assertStringContainsString('<a href="/accessibility/v2">Version 2, 2026-11-01</a>', $html);
    }

    public function testAVersionIsShownInFrenchWithTheWordingChosen(): void
    {
        $this->request('fr');
        $previous = StatementReader::fromArray(Statements::minimal(2));
        $current = StatementReader::fromArray(Statements::complete());
        $html = (string) $this->controller([$current, $previous], wording: 'aca', maxAge: 0)->version(2)->getContent();

        self::assertStringContainsString('<title>Déclaration d’accessibilité</title>', $html);
        self::assertStringContainsString('Notre organisation s’engage à rendre ce site accessible et à reconnaître', $html);
        self::assertStringContainsString('Loi canadienne sur l’accessibilité', $html);
        self::assertStringContainsString('La rétroaction peut être donnée de façon anonyme', $html);
        self::assertStringContainsString('Au regard des WCAG 2.2 AA, ce site est entièrement conforme', $html);
        self::assertStringContainsString('<a href="/accessibilite/v2" aria-current="page">Version 2, 2026-11-01</a>', $html);
        self::assertStringNotContainsString('aria-current="page">Version 3', $html);
        self::assertStringNotContainsString('Contenus non accessibles', $html);
        self::assertStringNotContainsString('Solutions de rechange', $html);
        self::assertStringNotContainsString('Voies de recours', $html);
        self::assertStringNotContainsString('évalué pour la dernière fois', $html);
        self::assertStringNotContainsString('Nous écrire', $html);

        $response = $this->controller([$current], maxAge: 0)->current();
        self::assertSame('no-cache, private', $response->headers->get('Cache-Control'));
        self::assertStringContainsString('<p>Texte gris des pages d’événements.</p>', (string) $response->getContent());
        self::assertMatchesRegularExpression('~criterion">'.preg_quote('1.4.3 Contraste (minimum) (niveau AA)', '~').'</h4>~', (string) $response->getContent());
        self::assertStringNotContainsString('Versions de cette déclaration', (string) $response->getContent());
    }

    public function testTheWordingOfTheEuropeanStandard(): void
    {
        $html = (string) $this->controller([StatementReader::fromArray(Statements::minimal())], wording: 'en-301-549')->current()->getContent();

        self::assertStringContainsString('Our organisation is committed to making this site accessible, as the European accessibility requirements ask.', $html);
        self::assertStringContainsString('harmonised standard EN 301 549', $html);
    }

    public function testThePartsLeftOutAreNotShown(): void
    {
        $statement = StatementReader::fromArray([
            ...Statements::minimal(),
            'status' => 'partial',
            'feedback' => ['phone' => '5555', 'response_days' => 1],
            'non_conformities' => [['criterion' => '1.4.3']],
            'enforcement' => ['procedure' => ['en' => 'Write to the ombudsman.'], 'url' => 'https://example.org/'],
            'evaluation' => ['evaluated_at' => '2026-10-01', 'method' => 'third-party', 'pages' => 1],
        ]);
        $html = (string) $this->controller([$statement])->current()->getContent();

        self::assertMatchesRegularExpression('~criterion">'.preg_quote('1.4.3 Contrast (Minimum) (level AA)', '~').'</h4>~', $html);
        self::assertStringNotContainsString('Found', $html);
        self::assertStringNotContainsString('Meanwhile', $html);
        self::assertStringNotContainsString('To be met', $html);
        self::assertStringNotContainsString('mailto:', $html);
        self::assertStringNotContainsString('<address>', $html);
        self::assertStringContainsString('<p>We answer within a day.</p>', $html);
        self::assertStringContainsString('<p><a href="https://example.org/">https://example.org/</a></p>', $html);
        self::assertStringContainsString('by an independent party. One page was evaluated.', preg_replace('/\s+/', ' ', $html) ?? '');
        self::assertStringNotContainsString('Read the evaluation report', $html);

        $authority = StatementReader::fromArray([...Statements::minimal(), 'enforcement' => ['procedure' => ['en' => 'Complain.'], 'authority' => ['en' => 'The ombudsman']], 'evaluation' => ['evaluated_at' => '2026-10-01']]);
        $html = (string) $this->controller([$authority])->current()->getContent();
        self::assertMatchesRegularExpression('~<p>\s*The ombudsman\s*</p>~', $html);
        self::assertStringContainsString('by our own assessment.', $html);
        self::assertStringNotContainsString('evaluated.', $html);
    }

    public function testAMissingStatementIsNotFound(): void
    {
        $controller = $this->controller([StatementReader::fromArray(Statements::minimal())]);

        try {
            $controller->version(2);
            self::fail('A missing version was found.');
        } catch (NotFoundHttpException $notFoundHttpException) {
            self::assertSame('No such version of the accessibility statement.', $notFoundHttpException->getMessage());
        }

        $this->expectExceptionObject(new NotFoundHttpException('No accessibility statement is published.'));
        $this->controller([])->current();
    }

    public function testThePageListsTheNewestVersionsOnly(): void
    {
        $statements = [];

        for ($version = StatementController::MAX_VERSIONS + 1; $version >= 1; --$version) {
            $statements[] = StatementReader::fromArray(Statements::minimal($version));
        }

        $html = (string) $this->controller($statements)->current()->getContent();

        self::assertSame(StatementController::MAX_VERSIONS, substr_count($html, '<a href="/accessibility/v'));
        self::assertStringNotContainsString('/accessibility/v1"', $html);
    }

    /**
     * @param list<Statement> $statements
     */
    private function controller(array $statements, string $wording = 'wcag', ?string $feedbackRoute = null, int $maxAge = 3600, ?string $feedbackForm = null): StatementController
    {
        return new StatementController(new MemoryStatements($statements), $this->twig(), PhilAccessibilityStatementBundle::LAYOUT, $wording, $feedbackRoute, $maxAge, $feedbackForm);
    }

    private function twig(): Environment
    {
        if (!self::$booted) {
            self::bootKernel();
            $this->request('en');
        }

        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig;
    }

    private function request(string $locale): void
    {
        if (!self::$booted) {
            self::bootKernel();
        }

        $request = Request::create('/');
        $request->setLocale($locale);

        $requests = self::getContainer()->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requests);
        $requests->push($request);
        $switcher = self::getContainer()->get('translation.locale_switcher');
        self::assertInstanceOf(LocaleSwitcher::class, $switcher);
        $switcher->setLocale($locale);
        $router = self::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);
        $router->getContext()->setParameter('_locale', $locale);
    }
}
