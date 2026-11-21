<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\NewsletterBundle\Application\Content\ContentRenderer;
use Phil\NewsletterBundle\Application\Content\LinkRewriter;
use Phil\NewsletterBundle\Application\Content\RangeRefusal;
use Phil\NewsletterBundle\Application\Content\RenderedMessage;
use Phil\NewsletterBundle\Application\Content\SandboxedTemplates;
use Phil\NewsletterBundle\Application\Content\TextPart;
use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;
use Phil\NewsletterBundle\Domain\Model\Template;
use Phil\NewsletterBundle\Tests\Unit\Fixture\Newsletter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Twig\Markup;

#[CoversClass(SandboxedTemplates::class)]
#[CoversClass(RangeRefusal::class)]
#[CoversClass(LinkRewriter::class)]
#[CoversClass(ContentRenderer::class)]
#[CoversClass(RenderedMessage::class)]
#[CoversClass(TextPart::class)]
final class ContentTest extends TestCase
{
    public function testASandboxedTemplateRendersWithTheFiltersAndTagsAllowed(): void
    {
        $sandbox = new SandboxedTemplates();
        $source = '{% set greeting = "hello" %}{% if items|length > 1 %}{% for item in items %}{{ item|upper }}{% if not loop.last %}, {% endif %}{% endfor %}{% endif %} {{ greeting|capitalize }} {{ html }} {{ markup }} {% apply lower %}OK{% endapply %}{% with {x: 1} %}{{ x }}{% endwith %}';

        self::assertSame('A, B Hello &lt;b&gt; <i>safe</i> ok1', $sandbox->render($source, ['items' => ['a', 'b'], 'html' => '<b>', 'markup' => new Markup('<i>safe</i>', 'UTF-8')]));
        self::assertSame('x', $sandbox->render('{{ value|default("x") }}', []));
        $sandbox->check('{{ value }}');
    }

    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('refused')]
    public function testASandboxedTemplateCannotReachTheApplication(string $source, array $context, string $message): void
    {
        $this->expectException(InvalidNewsletterValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        new SandboxedTemplates()->render($source, $context);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function refused(): iterable
    {
        yield 'a function' => ['{{ include("x") }}', [], 'The template cannot be rendered: Function "include" is not allowed'];
        yield 'a method' => ['{{ date.format("Y") }}', ['date' => new DateTimeImmutable()], 'Calling "format" method'];
        yield 'a property' => ['{{ object.public }}', ['object' => new class {
            public string $public = 'x';
        }], 'Calling "public" property'];
        yield 'a tag' => ['{% macro m() %}{% endmacro %}', [], 'Tag "macro" is not allowed'];
        yield 'a filter' => ['{{ "x"|raw }}', [], 'Filter "raw" is not allowed'];
        yield 'the range operator' => ['{% for i in 1..1000000000 %}{% endfor %}', [], 'A template of the newsletter cannot use the range operator ".."'];
        yield 'a syntax error' => ['{{ x', [], 'The template cannot be rendered: Unexpected token'];
        yield 'an object printed' => ['{{ date }}', ['date' => new DateTimeImmutable()], 'The template cannot be rendered'];
    }

    public function testCheckRefusesWhatDoesNotCompile(): void
    {
        $this->expectException(InvalidNewsletterValue::class);
        $this->expectExceptionMessageIsOrContains('The template does not compile: A template of the newsletter cannot use the range operator ".."');

        new SandboxedTemplates()->check('{{ 1..2|join }}');
    }

    public function testTheOutputIsBounded(): void
    {
        $sandbox = new SandboxedTemplates();
        $big = str_repeat('x', 1_048_576);
        self::assertSame(1_048_576, \strlen($sandbox->render('{{ big }}', ['big' => $big])));

        $this->expectException(InvalidNewsletterValue::class);
        $this->expectExceptionMessageIsOrContains('A template writes at most 1048576 bytes.');
        $sandbox->render('{{ big }}{{ big }}', ['big' => $big]);
    }

    public function testCompiledTemplatesAreKeptThenForgotten(): void
    {
        $sandbox = new SandboxedTemplates();
        $compiled = new ReflectionProperty(SandboxedTemplates::class, 'compiled');

        $sandbox->render('a', []);
        $sandbox->render('a', []);
        self::assertCount(1, (array) $compiled->getValue($sandbox));

        for ($i = 0; $i < 63; ++$i) {
            $sandbox->render('t'.$i, []);
        }

        self::assertCount(64, (array) $compiled->getValue($sandbox));
        self::assertSame('next', $sandbox->render('next', []));
        self::assertCount(1, (array) $compiled->getValue($sandbox));

        $sandbox->reset();
        self::assertCount(0, (array) $compiled->getValue($sandbox));
        self::assertSame('again', $sandbox->render('again', []));
    }

    public function testLinksAreRewrittenForTracking(): void
    {
        $rewriter = new LinkRewriter();
        $track = static fn (string $url): string => 'https://t.example/?u='.rawurlencode($url);

        self::assertSame('<p>no link</p>', $rewriter->rewrite('<p>no link</p>', $track));
        self::assertSame(
            '<p><a href="https://t.example/?u=https%3A%2F%2Fexample.com%2Fa%3Fb%3D1">a</a> <a href="mailto:a@example.com">m</a> <a href="#top">t</a> <a href="javascript:alert(1)">j</a> <a href="https://t.example/?u=http%3A%2F%2Fexample.org%2F">o</a> <a>none</a></p>',
            $rewriter->rewrite('<p><a href=" https://example.com/a?b=1 ">a</a> <a href="mailto:a@example.com">m</a> <a href="#top">t</a> <a href="javascript:alert(1)">j</a> <a href="http://example.org">o</a> <a>none</a></p>', $track),
        );
    }

    public function testACampaignIsLaidOutByTheHouseLayout(): void
    {
        $renderer = new Newsletter()->renderer();
        $message = $renderer->campaign('Subject', 'Pre', "Hello **you** <script>alert(1)</script>\n\n[link](https://example.com)", null, ['list_name' => 'News', 'site' => 'default', 'unsubscribe_url' => 'https://u.example', 'preferences_url' => 'https://p.example']);

        self::assertSame('Subject', $message->subject);
        self::assertStringContainsString('<title>Subject</title>', $message->html);
        self::assertStringContainsString('>Pre</div>', $message->html);
        self::assertStringContainsString('<strong>you</strong>', $message->html);
        self::assertStringNotContainsString('<script>', $message->html);
        self::assertStringContainsString('href="https://example.com"', $message->html);
        self::assertStringContainsString('href="https://u.example"', $message->html);
        self::assertStringContainsString('href="https://p.example"', $message->html);
        self::assertStringContainsString('You receive this message as a subscriber of News.', $message->html);
        self::assertSame("Hello you\n\nlink <https://example.com>\n\nYou receive this message as a subscriber of News.\n\nYour preferences <https://p.example> · Unsubscribe <https://u.example>", $message->text);
    }

    public function testACampaignIsLaidOutByAStoredTemplateAndItsLinksTracked(): void
    {
        $renderer = new Newsletter()->renderer();
        $layout = new Template('house', null, '', '<h1>{{ subject }}</h1>{{ content }}<small>{{ preheader }}</small>', new DateTimeImmutable());

        $message = $renderer->campaign('S', 'P', '[a](https://example.com/a)', $layout, [], static fn (string $url): string => 'https://t.example/1');

        self::assertSame('<h1>S</h1><p><a href="https://t.example/1">a</a></p><small>P</small>', $message->html);
    }

    public function testATemplateOnItsOwn(): void
    {
        $renderer = new Newsletter()->renderer();
        $message = $renderer->template(new Template('t', null, '  <b>Hi</b> {{ name }} &amp; co ', '<p>{{ subject }}</p>', new DateTimeImmutable()), ['name' => 'Ada']);

        self::assertSame('Hi Ada & co', $message->subject);
        self::assertSame('<p>Hi Ada &amp; co</p>', $message->html);
        self::assertSame('Hi Ada & co', $message->text);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function textParts(): iterable
    {
        yield 'nothing' => [" \n ", ''];
        yield 'the head, styles, scripts and what is hidden' => ['<html><head><title>T</title><style>p {}</style></head><body><div style="color: red; display : none">pre</div><p hidden>h</p><script>x()</script><template>t</template><noscript>n</noscript><svg><text>s</text></svg>Body</body></html>', 'Body'];
        yield 'a style that only mentions display' => ['<div style="display: block">shown</div><span style="xdisplay:none">too</span>', "shown\n\ntoo"];
        yield 'a link and its address' => ['<p>Read <a href=" https://e.example/a?b=1&amp;c=2 ">this</a>.</p>', 'Read this <https://e.example/a?b=1&c=2>.'];
        yield 'a link whose words are its address' => ['<a href="http://e.example/y">http://e.example/y</a>', 'http://e.example/y'];
        yield 'an image as the words of a link' => ['<a href="https://e.example/i"><img src="x.png" alt=" Logo "></a>', 'Logo <https://e.example/i>'];
        yield 'a link without words' => ['<a href="HTTPS://e.example/i"><img src="x.png"></a>', 'HTTPS://e.example/i'];
        yield 'links that lead to no page' => ['<a href="#top">top</a> <a href="mailto:a@example.com">mail</a> <a href="javascript:x()">js</a> <a href="https://e.example/a b">space</a> <a>none</a>', 'top mail js space none'];
        yield 'blocks, line breaks and lists' => ['<h1>Hi &amp; you</h1><p>one<br>two</p><ul><li> a </li><li>b</li></ul><table><tr><td>c</td><th>d</th></tr><tr><td>e</td></tr></table><p>  end  </p>', "Hi & you\n\none\ntwo\n\n- a\n- b\n\nc d\n\ne\n\nend"];
        yield 'blank lines folded' => ['<p>a</p><div></div><p></p><p>b</p>', "a\n\nb"];
    }

    #[DataProvider('textParts')]
    public function testTheTextPartKeepsTheAddressOfEachLink(string $html, string $text): void
    {
        self::assertSame($text, new TextPart()->convert($html, 'UTF-8'));
    }
}
