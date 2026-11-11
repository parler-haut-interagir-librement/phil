<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Tests\Functional;

use Override;
use Phil\Markup\Level;
use Phil\Markup\Markup;
use Phil\Markup\MarkupFormat;
use Phil\MarkupBundle\Infrastructure\Conversion\UjoConverter;
use Phil\MarkupBundle\Infrastructure\Form\MarkupFormatType;
use Phil\MarkupBundle\PhilMarkupBundle;
use Phil\MarkupBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;

#[CoversClass(PhilMarkupBundle::class)]
final class MarkupBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
    }

    public function testMarkupIsRenderedInTemplates(): void
    {
        $markup = $this->service(Markup::class);
        self::assertInstanceOf(Markup::class, $markup);
        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $page = $twig->render('page.html.twig', [
            'source' => "[b]Bonjour[/b] [url=https://example.com]site[/url]\n[h2]Titre[/h2]",
            'text' => $markup->text('<p>a<script>b</script></p><h2>c</h2>', MarkupFormat::Html, Level::All),
            'none' => null,
        ]);

        self::assertSame(<<<'HTML'
            <article><p><b>Bonjour</b> <a href="https://example.com">site</a></p>
            Titre</article>
            <aside><b>Bonjour</b> site
            Titre</aside>
            <section><p>a</p>c</section>
            <footer><h2>t</h2></footer>
            <pre>{&quot;blocks&quot;:[{&quot;id&quot;:&quot;b1&quot;,&quot;type&quot;:&quot;paragraph&quot;,&quot;data&quot;:{&quot;text&quot;:&quot;&lt;b&gt;a&lt;/b&gt;&quot;}}]}</pre>

            HTML, $page);
    }

    public function testTheFormatsHaveSmartPunctuationInTheLocaleOfTheRequest(): void
    {
        $markup = $this->service(Markup::class);
        self::assertInstanceOf(Markup::class, $markup);
        $switcher = $this->service(LocaleSwitcher::class);
        self::assertInstanceOf(LocaleSwitcher::class, $switcher);

        self::assertSame("<p>«\u{A0}Oui\u{A0}»\u{202F}! Voilà…</p>", $markup->toHtml('"Oui" ! Voilà...', MarkupFormat::Markdown, Level::Forum));
        self::assertSame('<p>“Yes” – <code>&#34;no&#34;</code></p>', $switcher->runWithLocale('en', static fn (): string => $markup->toHtml('<p>"Yes" -- <code>"no"</code></p>', MarkupFormat::Html, Level::All)));
    }

    public function testImagesComeOnlyFromTheConfiguredHosts(): void
    {
        $markup = $this->service(Markup::class);
        self::assertInstanceOf(Markup::class, $markup);

        self::assertSame('<img /><img src="https://cdn.example.com/a.png" />', $markup->toHtml('<img src="https://tracker.example/p.gif"><img src="https://cdn.example.com/a.png">', MarkupFormat::Html, Level::Forum));
    }

    public function testTheFormatsAreConvertedAndOffered(): void
    {
        $converter = $this->service(UjoConverter::class);
        self::assertInstanceOf(UjoConverter::class, $converter);
        $factory = $this->service('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $factory);
        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);

        self::assertSame('{"blocks":[{"id":"b2","type":"header","data":{"text":"Titre","level":2},"tunes":{"anchor":{"anchor":"titre"}}}]}', $converter->toJson('[h2]Titre[/h2]', 'bbcode'));
        self::assertSame('paragraph', $converter->read("[html]\n<i>x</i>\n[/html]", 'bbcode', 'all')->document->blocks[0]->type);
        self::assertSame('raw', $converter->read("<div>\n<i>x</i>\n</div>", 'markdown', 'all')->document->blocks[0]->type);
        self::assertSame(
            '<select id="form" name="form"><option value="markdown">Markdown</option><option value="bbcode">BBCode</option><option value="html">HTML</option><option value="text">Texte brut</option></select>',
            preg_replace('/\s+(?=<)|(?<=>)\s+/', '', $twig->createTemplate('{{ form_widget(form) }}')->render(['form' => $factory->createNamed('form', MarkupFormatType::class)->createView()])),
        );
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }
}
