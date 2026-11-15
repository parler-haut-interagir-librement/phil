<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Tests\Unit\Infrastructure;

use Phil\AccessibilityStatement\LocalizedText;
use Phil\AccessibilityStatementBundle\Infrastructure\Twig\KitComponents;
use Phil\AccessibilityStatementBundle\Infrastructure\Twig\StatementExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\PhpFileLoader as RoutesLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

/**
 * The footer link, the path, the text filter and the names of the recipes, with the translations and the routes of the
 * bundle.
 */
#[CoversClass(StatementExtension::class)]
final class StatementExtensionTest extends TestCase
{
    private const string ROOT = __DIR__.'/../../..';

    private RequestStack $requests;

    private Translator $translator;

    private UrlGenerator $urls;

    protected function setUp(): void
    {
        $this->requests = new RequestStack();
        $this->translator = new Translator('en');
        $this->translator->setFallbackLocales(['en']);
        $this->translator->addLoader('php', new PhpFileLoader());

        foreach (['en', 'fr'] as $locale) {
            $this->translator->addResource('php', self::ROOT.'/translations/phil_accessibility_statement.'.$locale.'.php', $locale, StatementExtension::DOMAIN);
        }

        $routes = new RoutesLoader(new FileLocator(self::ROOT.'/config'))->load('routes.php');
        $routes->add('feedback', new Route('/contact'));

        $this->urls = new UrlGenerator($routes, new RequestContext(), defaultLocale: 'en');
    }

    public function testTheFooterLinkIsTranslatedAndEscaped(): void
    {
        $extension = new StatementExtension($this->urls, $this->requests, 'en', $this->translator);

        self::assertSame('<a href="/accessibility">Accessibility</a>', $extension->link());
        self::assertSame('<a href="/accessibility" class="footer-link" data-x="&quot;&lt;&gt;">A &amp; B</a>', $extension->link('A & B', ['class' => 'footer-link', 'data-x' => '"<>', 'href' => '/evil', 'on click' => 'x', 'Style' => 'x', 'title' => ['x'], str_repeat('a', 65) => 'x', "lang\n" => 'x']));
        self::assertSame("<a href=\"/accessibility\">It&apos;s &lt;\u{FFFD}&gt;</a>", $extension->link("It's <\xFF>"));
        self::assertSame('<a href="/accessibility" tabindex="0">Accessibility</a>', $extension->link(attributes: ['tabindex' => 0]));
        self::assertSame('<a href="/accessibility">Accessibility</a>', new StatementExtension($this->urls, $this->requests, 'en')->link());
        self::assertSame(['Card:Title', 'ui:Card:Title'], [$extension->kit('Card:Title'), new StatementExtension($this->urls, $this->requests, 'en', kit: new KitComponents('ui'))->kit('Card:Title')]);

        $this->request('fr');
        self::assertSame('<a href="/accessibilite">Accessibilité</a>', $extension->link());
        self::assertSame('/accessibilite', $extension->path());
        self::assertSame('Bonjour', $extension->text(new LocalizedText(['en' => 'Hello', 'fr' => 'Bonjour'])));

        $this->request('de');
        self::assertSame('Hello', $extension->text(new LocalizedText(['es' => 'Hola', 'en' => 'Hello'])));
    }

    public function testTheFunctionsAndTheFilterAreDeclaredToTwig(): void
    {
        $this->request('fr');
        $twig = $this->twig();
        $text = new LocalizedText(['es' => 'Hola', 'fr' => 'Bonjour']);

        self::assertSame(
            '<a href="/accessibilite">Accessibilité</a> /accessibilite Bonjour Badge',
            $twig->createTemplate("{{ phil_accessibility_statement_link() }} {{ phil_accessibility_statement_path() }} {{ text|phil_accessibility_text }} {{ phil_accessibility_kit('Badge') }}")->render(['text' => $text]),
        );
        self::assertSame('&lt;b&gt;', $twig->createTemplate('{{ text|phil_accessibility_text }}')->render(['text' => LocalizedText::of('<b>')]));
        self::assertSame('Hola', new StatementExtension($this->urls, new RequestStack(), 'es')->text($text));
    }

    private function twig(): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath(self::ROOT.'/templates', 'PhilAccessibilityStatement');

        $extension = new StatementExtension($this->urls, $this->requests, 'en', $this->translator);
        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension($this->translator));
        $twig->addExtension(new RoutingExtension($this->urls));
        $twig->addExtension(new AttributeExtension(StatementExtension::class));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([StatementExtension::class => static fn (): StatementExtension => $extension]));
        $twig->addGlobal('app', ['request' => $this->requests->getCurrentRequest() ?? Request::create('/')]);

        return $twig;
    }

    private function request(string $locale): void
    {
        $request = Request::create('/');
        $request->setLocale($locale);

        $this->requests->push($request);
        $this->translator->setLocale($locale);
        $this->urls->getContext()->setParameter('_locale', $locale);
    }
}
