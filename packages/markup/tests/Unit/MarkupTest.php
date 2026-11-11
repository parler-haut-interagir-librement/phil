<?php

declare(strict_types=1);

namespace Phil\Markup\Tests\Unit;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\Markup\Adapter\CodeClasses;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\PlainText;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Exception\InvalidSource;
use Phil\Markup\Exception\UnknownFormat;
use Phil\Markup\Level;
use Phil\Markup\MarkedUpText;
use Phil\Markup\Markup;
use Phil\Markup\MarkupCompiler;
use Phil\Markup\MarkupFormat;
use Phil\Markup\Reader\BBCode\BBCodeReader;
use Phil\Markup\Reader\Contract\ReaderProvider;
use Phil\Markup\Reader\Creole\CreoleReader;
use Phil\Markup\Reader\DokuWiki\DokuWikiReader;
use Phil\Markup\Reader\Html\HtmlReader;
use Phil\Markup\Reader\Markdown\BaseDialect;
use Phil\Markup\Reader\Markdown\MarkdownReader;
use Phil\Markup\Reader\MarkupReader;
use Phil\Markup\Reader\MediaWiki\MediaWikiReader;
use Phil\Markup\Reader\UjoReader;
use Phil\Markup\Tests\Unit\Fixtures\Post;
use Phil\Ujo\Block;
use Phil\Ujo\Exception\InvalidJson;
use Phil\Ujo\Normalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Markup::class)]
#[CoversClass(MarkedUpText::class)]
#[CoversClass(DefaultCompiler::class)]
#[CoversClass(PlainText::class)]
#[CoversClass(SanitizerPurifier::class)]
#[CoversClass(CodeClasses::class)]
#[CoversClass(MarkupFormat::class)]
#[CoversClass(Level::class)]
#[CoversClass(UjoReader::class)]
#[CoversClass(InvalidSource::class)]
#[CoversClass(UnknownFormat::class)]
final class MarkupTest extends TestCase
{
    private const string MARKDOWN = "# Titre\n\nUn **gras** et [lien](https://example.com \"Site\") ![Logo](https://example.com/a.png)\n\n- un\n\n> cité\n\n| a |\n|---|\n| b |\n\n<script>x</script>\n\n[js](javascript:alert)";

    public function testEveryFormatCompiles(): void
    {
        $compiler = new DefaultCompiler();
        $ujo = '{"blocks":[{"id":"a","type":"paragraph","data":{"text":"<strong>x</strong>"}},{"id":"r","type":"raw","data":{"html":"<p>r</p>"}}]}';

        self::assertSame('<p class="ujo-paragraph"><b>a</b> <a href="https://example.com">https://example.com</a></p>', $compiler->compile('**a** https://example.com', MarkupFormat::Markdown));
        self::assertSame('<p class="ujo-paragraph">&lt;b&gt;x&lt;/b&gt; y</p>', $compiler->compile('<b>x</b> [y](javascript:z)', MarkupFormat::Markdown));
        self::assertSame('<p class="ujo-paragraph"><b>x</b> y</p>', $compiler->compileFor('<b>x</b> [y](javascript:z)', MarkupFormat::Markdown, Level::All));
        self::assertSame('<p class="ujo-paragraph">&lt;b&gt;x&lt;/b&gt;</p>', $compiler->compileFor('<b>x</b>', MarkupFormat::Markdown, Level::Basic));
        self::assertSame('<p class="ujo-paragraph">x^2^</p>', $compiler->compile('x^2^', MarkupFormat::Markdown));
        self::assertSame("<p class=\"ujo-paragraph\"><b>x</b></p>\n<div class=\"ujo-raw\"><p>r</p></div>", $compiler->compile($ujo, MarkupFormat::Ujo));
        self::assertSame('<b onclick="x">a</b>', $compiler->compile('<b onclick="x">a</b>', MarkupFormat::Html));
        self::assertSame('<p class="ujo-paragraph"><b>a</b></p>', $compiler->compile('[b]a[/b]', MarkupFormat::BBCode));
        self::assertSame('<p class="ujo-paragraph">&lt;b&gt;</p>', $compiler->compile('[html]<b>[/html]', MarkupFormat::BBCode));
        self::assertSame('<div class="ujo-raw"><b></b></div>', $compiler->compileFor('[html]<b>[/html]', MarkupFormat::BBCode, Level::All));
        self::assertSame('<p>a</p>', $compiler->compile('a', MarkupFormat::Text));
        self::assertSame('<p class="ujo-paragraph"><b>a</b> &lt;b&gt;</p>', $compiler->compile("'''a''' <html><b></html>", MarkupFormat::MediaWiki));
        self::assertSame('<p class="ujo-paragraph"><b>a</b> <b></b></p>', $compiler->compileFor("'''a''' <html><b></html>", MarkupFormat::MediaWiki, Level::All));
        self::assertSame('<p class="ujo-paragraph"><i>a</i> &lt;b&gt;</p>', $compiler->compile('//a// <html><b></html>', MarkupFormat::DokuWiki));
        self::assertSame('<p class="ujo-paragraph"><i>a</i> <b></b></p>', $compiler->compileFor('//a// <html><b></html>', MarkupFormat::DokuWiki, Level::All));
        self::assertSame('<p class="ujo-paragraph"><b>a</b> &lt;b&gt;</p>', $compiler->compile('**a** <<html>><b><</html>>', MarkupFormat::Creole));
        self::assertSame('<p class="ujo-paragraph"><b>a</b> <b></b></p>', $compiler->compileFor('**a** <<html>><b><</html>>', MarkupFormat::Creole, Level::All));
        self::assertSame(['markdown', 'html', 'bbcode', 'mediawiki', 'dokuwiki', 'creole', 'text', 'ujo'], MarkupFormat::values());
        self::assertSame(['basic', 'forum', 'all'], array_map(static fn (Level $level): string => $level->value, Level::cases()));
    }

    public function testEachFormatWithAReaderGivesIt(): void
    {
        $compiler = new DefaultCompiler();

        self::assertInstanceOf(MarkdownReader::class, $compiler->reader(MarkupFormat::Markdown));
        self::assertInstanceOf(UjoReader::class, $compiler->reader(MarkupFormat::Ujo));
        self::assertInstanceOf(HtmlReader::class, $compiler->reader(MarkupFormat::Html));
        self::assertInstanceOf(BBCodeReader::class, $compiler->reader(MarkupFormat::BBCode));
        self::assertInstanceOf(MediaWikiReader::class, $compiler->reader(MarkupFormat::MediaWiki));
        self::assertInstanceOf(DokuWikiReader::class, $compiler->reader(MarkupFormat::DokuWiki));
        self::assertInstanceOf(CreoleReader::class, $compiler->reader(MarkupFormat::Creole));
        self::assertSame('raw', $compiler->reader(MarkupFormat::MediaWiki)->read("<html>\n<b></b>\n</html>")->document->blocks[0]->type);
        self::assertSame('paragraph', $compiler->reader(MarkupFormat::DokuWiki, Level::Forum)->read("<html>\n<b></b>\n</html>")->document->blocks[0]->type);
        self::assertSame('raw', $compiler->reader(MarkupFormat::Creole)->read("<<html>>\n<b></b>\n<</html>>")->document->blocks[0]->type);
        self::assertSame('header', $compiler->reader(MarkupFormat::Html)->read('<h2>a</h2>')->document->blocks[0]->type);
        self::assertSame(['paragraph'], array_map(static fn (Block $block): string => $block->type, $compiler->reader(MarkupFormat::Text)->read('a')->document->blocks));
        self::assertSame('raw', $compiler->reader(MarkupFormat::Markdown)->read('<div>x</div>')->document->blocks[0]->type);
        self::assertSame('paragraph', $compiler->reader(MarkupFormat::Markdown, Level::Forum)->read('<div>x</div>')->document->blocks[0]->type);
        self::assertSame('paragraph', $compiler->reader('markdown', Level::Forum)->read('<div>x</div>')->document->blocks[0]->type);
        self::assertSame('<p>a</p>', $compiler->compile('a', 'text'));
        self::assertSame('<b>a</b>', $compiler->compile('<b>a</b>', 'html'));
        self::assertSame(['title' => 'T'], $compiler->reader(MarkupFormat::Markdown)->read("---\ntitle: T\n---\nx")->metadata);
    }

    /**
     * The format of an add-on is read by the reader its provider gives, at the level asked, and rendered as the
     * built-in formats are; it is known by its name.
     */
    public function testAnAddOnBringsAFormatByName(): void
    {
        $provider = new class implements ReaderProvider {
            public function reader(Level $level, Normalizer $normalizer): MarkupReader
            {
                return new MarkdownReader(Level::All === $level, $normalizer, new class extends BaseDialect {
                });
            }
        };
        $compiler = new DefaultCompiler(null, ['shout' => $provider, 'a-1' => $provider, 'abcdefghijklmnop' => $provider]);

        self::assertSame(['markdown', 'html', 'bbcode', 'mediawiki', 'dokuwiki', 'creole', 'text', 'ujo', 'shout', 'a-1', 'abcdefghijklmnop'], $compiler->formats());
        self::assertSame('<p class="ujo-paragraph"><b>a</b></p>', $compiler->compile('**a**', 'shout'));
        self::assertSame('<div class="ujo-raw"><div>x</div></div>', $compiler->compileFor('<div>x</div>', 'shout', Level::All));
        self::assertSame('<p class="ujo-paragraph">&lt;div&gt;x&lt;/div&gt;</p>', $compiler->compileFor('<div>x</div>', 'shout', Level::Forum));
        self::assertInstanceOf(MarkdownReader::class, $compiler->reader('shout'));

        $markup = new Markup($compiler, SanitizerPurifier::defaultPolicies());
        self::assertSame('shout', $markup->text('a', 'shout')->format);
        self::assertSame('<p>a</p>', $markup->toHtml('a', 'shout'));
        self::assertSame('markdown', MarkupFormat::nameOf(MarkupFormat::Markdown));
        self::assertSame('shout', MarkupFormat::nameOf('shout'));
    }

    public function testAFormatNoReaderKnowsIsRefused(): void
    {
        try {
            new DefaultCompiler(null, ['shout' => self::createStub(ReaderProvider::class)])->compile('a', 'textile');
            self::fail('A format no reader knows is refused.');
        } catch (UnknownFormat $unknownFormat) {
            self::assertSame('The format "textile" is not known; use one of: markdown, html, bbcode, mediawiki, dokuwiki, creole, text, ujo, shout.', $unknownFormat->getMessage());
            self::assertSame(['markdown', 'html', 'bbcode', 'mediawiki', 'dokuwiki', 'creole', 'text', 'ujo', 'shout'], $unknownFormat->known);
        }

        $this->expectException(UnknownFormat::class);
        new DefaultCompiler()->reader('textile');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badNames(): iterable
    {
        yield 'built-in' => ['markdown'];
        yield 'upper case' => ['Shout'];
        yield 'digit first' => ['1shout'];
        yield 'too long' => ['abcdefghijklmnopq'];
        yield 'empty' => [''];
        yield 'space' => ['a b'];
        yield 'new line after' => ["shout\n"];
    }

    #[DataProvider('badNames')]
    public function testAnAddOnCannotTakeANameThatIsNotFree(string $name): void
    {
        $this->expectException(UnknownFormat::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" cannot name a format of an add-on: a name is 1 to 16 lower-case letters, digits and hyphens, starts with a letter, and is not already a format.', $name));

        new DefaultCompiler(null, [$name => self::createStub(ReaderProvider::class)]);
    }

    public function testAUjoSourceThatIsNotValidIsRefused(): void
    {
        try {
            new DefaultCompiler()->compile('{"blocks": [', MarkupFormat::Ujo);
            self::fail('A source that is not JSON is refused.');
        } catch (InvalidSource $invalidSource) {
            self::assertStringStartsWith('The ujo source cannot be read: ', $invalidSource->getMessage());
            self::assertInstanceOf(InvalidJson::class, $invalidSource->getPrevious());
        }

        $this->expectException(InvalidSource::class);
        new UjoReader()->read('{"blocks":[{"id":"r","type":"raw","data":{"html":"x"}}]}');
    }

    public function testACompilerThatDoesNotKnowLevelsCompilesOnce(): void
    {
        $compiler = new class implements MarkupCompiler {
            public function compile(string $source, MarkupFormat|string $format): string
            {
                return '<p>'.$source.'</p><script>x</script>';
            }
        };

        self::assertSame('<p>a</p>', new Markup($compiler, SanitizerPurifier::defaultPolicies())->toHtml('a', MarkupFormat::Markdown, Level::Forum));
    }

    public function testPlainTextKeepsItsParagraphs(): void
    {
        self::assertSame("<p>a<br>\nb</p>", PlainText::compile("a\r\nb"));
        self::assertSame("<p>Ligne<br>\nDeux &lt;b&gt;</p>\n<p>Para <a href=\"https://example.com\">https://example.com</a>.</p>", PlainText::compile("\n  Ligne\r\nDeux <b>\n \n\n  Para https://example.com.  \n"));
        self::assertSame('', PlainText::compile(" \n\n "));
    }

    public function testEachLevelKeepsWhatItAllows(): void
    {
        $markup = new Markup(new DefaultCompiler(), SanitizerPurifier::defaultPolicies());

        $basic = $markup->toHtml(self::MARKDOWN, MarkupFormat::Markdown, Level::Basic);
        self::assertSame('Titre Un <b>gras</b> et lien un cité ab &lt;script&gt;x&lt;/script&gt; js', $this->words($basic));
        self::assertStringNotContainsString('<img', $basic);

        $forum = $markup->toHtml(self::MARKDOWN, MarkupFormat::Markdown, Level::Forum);
        self::assertSame('Titre <p>Un <b>gras</b> et <a href="https://example.com">lien</a></p> <img src="https://example.com/a.png" alt="Logo" /> <ul><li>un</li></ul> <blockquote>cité</blockquote> ab <p>&lt;script&gt;x&lt;/script&gt;</p> <p>js</p>', $this->words($forum));

        $all = $markup->toHtml(self::MARKDOWN, MarkupFormat::Markdown);
        self::assertStringContainsString('<h1 id="titre">Titre</h1>', $all);
        self::assertStringContainsString('<td>b</td>', $all);
        self::assertStringNotContainsString('script', $all, 'Raw HTML is kept at "all", then purified.');
        self::assertSame("<p><b>b</b></p>\n<div><div>block</div></div>", $markup->toHtml("<b onclick=x>b</b>\n\n<div>block</div>", MarkupFormat::Markdown));

        self::assertSame('<p>a</p>', $markup->toHtml('<p onclick="x" style="color:red">a<script>b</script><iframe>c</iframe><object>d</object></p>', MarkupFormat::Html, Level::Forum));
        self::assertSame('<a>a</a><a href="/b">b</a><a href="mailto:a&#64;b.ca">c</a><img src="/d.png" /><img />', $markup->toHtml('<a href="ftp://x">a</a><a href="/b">b</a><a href="mailto:a@b.ca">c</a><img src="/d.png"><img src="data:image/png;base64,AA==">', MarkupFormat::Html, Level::Forum));
        self::assertSame('<del>a</del><sub>b</sub><sup>c</sup><code>d</code><u>e</u><s>f</s><em>g</em><i>h</i><b>i</b><br />', $markup->toHtml('<del>a</del><sub>b</sub><sup>c</sup><code>d</code><u>e</u><s>f</s><em>g</em><i>h</i><b>i</b><br>', MarkupFormat::Html, Level::Basic));
        self::assertSame('<p>a</p><ol><li>b</li></ol><pre>c</pre><hr /><blockquote><cite>d</cite></blockquote>e', $markup->toHtml('<p>a</p><ol><li>b</li></ol><pre>c</pre><hr><blockquote><cite>d</cite></blockquote><h2>e</h2>', MarkupFormat::Html, Level::Forum));
        self::assertSame(str_repeat('a', 30000), $markup->toHtml(str_repeat('a', 30000), MarkupFormat::Html, Level::All));
        self::assertSame('a', $markup->toHtml('<span>a</span>', MarkupFormat::Html, Level::Forum));
        self::assertSame('<a href="http://example.com">a</a><img src="http://example.com/a.png" />', $markup->toHtml('<a href="http://example.com">a</a><img src="http://example.com/a.png">', MarkupFormat::Html, Level::Forum));
    }

    public function testTheLevelAllKeepsMathMlCore(): void
    {
        $html = '<p>a<math display="block"><mi mathvariant="bold">x</mi><mfrac><mn>1</mn><mn>2</mn></mfrac></math></p>';

        self::assertSame($html, SanitizerPurifier::defaultPolicies()->purify($html, Level::All));
        self::assertSame('<p>a</p>', SanitizerPurifier::defaultPolicies()->purify($html, Level::Forum));
    }

    /**
     * An image of another site is a request every reader's browser makes to it: the hosts given are the only ones kept,
     * subdomains included, and the site's own images always.
     */
    public function testImagesComeOnlyFromTheHostsGiven(): void
    {
        $html = '<img src="https://tracker.example/p.gif"><img src="https://cdn.example.com/a.png"><img src="https://img.cdn.example.com/b.png"><img src="/c.png">';
        $restricted = new Markup(new DefaultCompiler(), SanitizerPurifier::defaultPolicies(['cdn.example.com']));
        $none = new Markup(new DefaultCompiler(), SanitizerPurifier::defaultPolicies([]));

        foreach ([Level::Forum, Level::All] as $level) {
            self::assertSame('<img /><img src="https://cdn.example.com/a.png" /><img src="https://img.cdn.example.com/b.png" /><img src="/c.png" />', $restricted->toHtml($html, MarkupFormat::Html, $level));
            self::assertSame('<img /><img /><img /><img src="/c.png" />', $none->toHtml($html, MarkupFormat::Html, $level));
        }

        self::assertSame('<img alt />', $restricted->toHtml('[img]https://tracker.example/p.gif[/img]', MarkupFormat::BBCode, Level::Forum));
        self::assertStringContainsString('src="https://tracker.example/p.gif"', new Markup(new DefaultCompiler(), SanitizerPurifier::defaultPolicies())->toHtml($html, MarkupFormat::Html, Level::Forum), 'Any host by default.');
    }

    /**
     * The language of a code block, for a highlighter in the browser, and the classes of code highlighted on the server
     * are the only classes kept.
     */
    public function testCodeKeepsItsClasses(): void
    {
        $purifier = SanitizerPurifier::defaultPolicies();
        $html = '<pre class="x"><code class="language-php  x language-c++ language-'.str_repeat('a', 33).'"><span class="hl-keyword evil hl-attr-name hl-'.str_repeat('a', 33).'">echo</span><span class="x">1</span></code></pre><p class="hl-keyword">a</p><code class="x">b</code>';

        self::assertSame('<pre><code class="language-php language-c&#43;&#43;"><span class="hl-keyword hl-attr-name">echo</span><span>1</span></code></pre><p>a</p><code>b</code>', $purifier->purify($html, Level::All));
        self::assertSame('<pre><code class="language-php language-c&#43;&#43;">echo1</code></pre><p>a</p><code>b</code>', $purifier->purify($html, Level::Forum));
        self::assertSame('<code class="language-php language-c&#43;&#43;">echo1</code>a<code>b</code>', $purifier->purify($html, Level::Basic));
        self::assertSame('<code class="language-a1_#.-B">c</code><span class="hl-a-b">d</span><code>e</code>', $purifier->purify('<code class="language-a1_#.-B">c</code><span class="hl-a-b">d</span><code class="Language-x">e</code>', Level::All));
        self::assertSame('<code class="language-a language-b language-c language-d">e</code>', $purifier->purify("<code class=\"language-a\tlanguage-b\nlanguage-c\rlanguage-d\">e</code>", Level::All));
        self::assertSame(['code', 'span'], new CodeClasses()->getSupportedElements());
        self::assertSame(['class'], new CodeClasses()->getSupportedAttributes());
    }

    public function testATextKeepsItsSourceAndItsHtml(): void
    {
        $markup = new Markup(new DefaultCompiler(), SanitizerPurifier::defaultPolicies());
        $text = $markup->text('[b]Bonjour[/b] [img]https://example.com/a.png[/img]', MarkupFormat::BBCode, Level::Basic);

        self::assertSame(['[b]Bonjour[/b] [img]https://example.com/a.png[/img]', 'bbcode', "<b>Bonjour</b>\n"], [$text->source, $text->format, $text->html]);
        self::assertSame('<h2>a</h2>', $markup->text('<h2>a</h2>', MarkupFormat::Html)->html);

        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__.'/Fixtures']));
        $config->enableNativeLazyObjects(true);

        $manager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        new SchemaTool($manager)->createSchema($manager->getMetadataFactory()->getAllMetadata());
        $manager->persist(new Post('p', $text));
        $manager->flush();
        $manager->clear();

        self::assertSame(['[b]Bonjour[/b] [img]https://example.com/a.png[/img]', 'bbcode', "<b>Bonjour</b>\n"], $manager->getConnection()->fetchNumeric('SELECT body_source, body_format, body_html FROM post'));
        self::assertSame(16, $manager->getClassMetadata(Post::class)->getFieldMapping('body.format')->length);
        $post = $manager->find(Post::class, 'p');
        self::assertInstanceOf(Post::class, $post);
        self::assertEquals($text, $post->body);
    }

    private function words(string $html): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $html));
    }
}
