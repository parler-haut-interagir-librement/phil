<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderContext;
use Phil\EmailTemplatesBundle\Application\Rendering\Theme;
use Phil\EmailTemplatesBundle\Domain\Exception\RenderingFailed;
use Phil\EmailTemplatesBundle\Domain\Schema\VariableSchema;
use Phil\EmailTemplatesBundle\Domain\Status\BodyFormat;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\EmailComponents;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\LimitsVisitor;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\RenderMode;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\RenderStash;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\SandboxedEngine;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\SandboxRules;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\SourceLoader;
use Phil\EmailTemplatesBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

#[CoversClass(SandboxedEngine::class)]
#[CoversClass(SandboxRules::class)]
#[CoversClass(LimitsVisitor::class)]
#[CoversClass(SourceLoader::class)]
#[CoversClass(RenderStash::class)]
#[CoversClass(RenderMode::class)]
final class SandboxedEngineTest extends TestCase
{
    private SandboxedEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new SandboxedEngine(Theme::create(), Stage::markdown());
    }

    public function testTheBodyIsEscapedAsHtmlAndTheTextPartsAreNot(): void
    {
        $parts = $this->engine->render(
            $this->definition('Hi {{ name }}  &   co', '<p>{{ name }}</p>', preheader: "Line\n  {{ name }}", text: "Text {{ name }}\n\n"),
            ['name' => '<b>Ada & Co</b>'],
            $this->context(),
        );

        self::assertSame('Hi <b>Ada & Co</b> & co', $parts->subject);
        self::assertSame('Line <b>Ada & Co</b>', $parts->preheader);
        self::assertSame('<p>&lt;b&gt;Ada &amp; Co&lt;/b&gt;</p>', $parts->html);
        self::assertSame("Text <b>Ada & Co</b>\n", $parts->text);
    }

    public function testAnEmptyPreheaderAndNoTextPart(): void
    {
        $parts = $this->engine->render($this->definition('Hi', '<p>Body</p>'), [], $this->context());

        self::assertSame('', $parts->preheader);
        self::assertNull($parts->text);
    }

    public function testTheContextOfTheRenderIsTheEmailVariable(): void
    {
        $parts = $this->engine->render($this->definition('{{ email.key }}|{{ email.site }}|{{ email.locale }}', '{{ email.segments|join(",") }}', segments: ['vip', 'new']), [], $this->context(['vip', 'new']));

        self::assertSame('greeting|main|fr_CA', $parts->subject);
        self::assertSame('vip,new', $parts->html);
    }

    public function testAMarkdownBodyIsHtmlWithItsComponentsKeptWhole(): void
    {
        $definition = $this->definition('Hi', "# Hello {{ name }}\n\nSee {{ button('Open', link) }} now.\n{% apply panel %}**Bold** note{% endapply %}\nAn {{ image('https://example.com/a.png', 'icon') }} inline.", BodyFormat::Markdown);
        $html = $this->engine->render($definition, ['name' => 'Ada <i>', 'link' => 'https://example.com/x'], $this->context())->html;

        self::assertStringContainsString('Hello Ada &lt;i&gt;</h1>', $html);
        self::assertStringContainsString('<p>See</p>', $html);
        self::assertStringContainsString('class="phil-email-button"', $html);
        self::assertStringContainsString('style="display:inline-block;padding:12px 24px;', $html);
        self::assertStringContainsString('<p>now.</p>', $html);
        self::assertStringContainsString('<p><strong>Bold</strong> note</p></td>', $html);
        self::assertStringContainsString('<p>An <img src="https://example.com/a.png" alt="icon" style="display:block;', $html);
        self::assertStringNotContainsString('<p><table', $html);
        self::assertDoesNotMatchRegularExpression('/phil[0-9a-f]{16}o[0-9]+z/', $html);
    }

    public function testAPlaceholderWrittenByHandIsLeftAsWritten(): void
    {
        $html = $this->engine->render($this->definition('Hi', "phil0123456789abcdefo0z and {{ button('Go', 'https://example.com/') }}", BodyFormat::Markdown), [], $this->context())->html;

        self::assertStringContainsString('phil0123456789abcdefo0z', $html);
        self::assertStringContainsString('class="phil-email-button"', $html);
    }

    public function testAMarkdownBodyWithoutAMarkdownRendererIsKeptAsWritten(): void
    {
        $engine = new SandboxedEngine(Theme::create());

        self::assertSame("Hi **Ada**\n", $engine->render($this->definition('Hi', "Hi **{{ name }}**\n", BodyFormat::Markdown), ['name' => 'Ada'], $this->context())->html);
        self::assertSame('x', '' === $engine->render($this->definition('Hi', '{% apply panel %}x{% endapply %}', BodyFormat::Markdown), [], $this->context())->html ? '' : 'x');
    }

    public function testSegmentsDecideTheConditionalBlocks(): void
    {
        $definition = $this->definition('Hi', '{% if segment("vip") %}VIP{% else %}All{% endif %}', segments: ['vip']);

        self::assertSame('VIP', $this->engine->render($definition, [], $this->context(['vip']))->html);
        self::assertSame('All', $this->engine->render($definition, [], $this->context())->html);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedSources(): iterable
    {
        yield 'include tag' => ['{% include "x" %}', 'The body: Tag "include" is not allowed.'];
        yield 'include function' => ['{{ include("x") }}', 'Function "include" is not allowed'];
        yield 'extends' => ['{% extends "x" %}', 'Tag "extends" is not allowed'];
        yield 'macro' => ['{% macro m() %}{% endmacro %}', 'Tag "macro" is not allowed'];
        yield 'block' => ['{% block b %}{% endblock %}', 'Tag "block" is not allowed'];
        yield 'constant test' => ['{% if 1 is constant("PHP_VERSION") %}{% endif %}', 'Test "constant" is not allowed'];
        yield 'constant function' => ['{{ constant("PHP_VERSION") }}', 'Function "constant" is not allowed'];
        yield 'range' => ['{% for i in 1..3 %}{% endfor %}', 'Function "range" is not allowed'];
        yield 'attribute' => ['{{ attribute(email, "key") }}', 'Function "attribute" is not allowed'];
        yield 'source' => ['{{ source("x") }}', 'Function "source" is not allowed'];
        yield 'dump' => ['{{ dump() }}', 'Unknown "dump" function.'];
        yield 'map filter' => ['{{ [1]|map(v => v)|join }}', 'Filter "map" is not allowed'];
        yield 'raw filter' => ['{{ "x"|raw }}', 'Filter "raw" is not allowed'];
        yield 'syntax' => ['{% if %}', 'The body (line 1): '];
        yield 'nested loops' => ['{% for a in [1] %}{% for b in [1] %}{% for c in [1] %}{% endfor %}{% endfor %}{% endfor %}', 'Loops nest at most 2 deep.'];
    }

    #[DataProvider('refusedSources')]
    public function testTheSandboxRefuses(string $body, string $error): void
    {
        $errors = $this->engine->check($this->definition('Hi', $body));

        self::assertNotSame([], array_filter($errors, static fn (string $message): bool => str_contains($message, $error)), implode("\n", $errors));

        $this->expectException(RenderingFailed::class);
        $this->engine->render($this->definition('Hi', $body), [], $this->context());
    }

    public function testAMethodOutsideTheListIsRefusedAtRunTime(): void
    {
        $definition = $this->definition('Hi', '{{ at.format("Y") }} {{ at.modify("+1 day").format("Y") }}', schema: ['at' => ['type' => 'datetime']]);

        self::assertSame([], $this->engine->check($definition));
        $this->expectException(RenderingFailed::class);
        $this->expectExceptionMessageIsOrContains('Calling "modify" method on a "DateTimeImmutable" object is not allowed');

        $this->engine->render($definition, ['at' => new DateTimeImmutable('2026-01-01')], $this->context());
    }

    public function testTheAllowedMethodsOfADate(): void
    {
        $definition = $this->definition('Hi', '{{ at.format("Y") }} {{ at.timestamp }} {{ at|date("d", false) }}', schema: ['at' => ['type' => 'datetime']]);

        self::assertSame('2026 1767225600 01', $this->engine->render($definition, ['at' => new DateTimeImmutable('2026-01-01 00:00:00 UTC')], $this->context())->html);
    }

    public function testTheAllowedFiltersAndTests(): void
    {
        $body = '{{ name|upper }} {{ name|lower }} {{ name|title }} {{ name|capitalize }} {{ " x "|trim }} {{ "a,b"|split(",")|join("+") }} {{ [1,2]|first }}{{ [1,2]|last }} {{ [1,2]|length }} {{ (-3)|abs }} {{ 2.567|round(1) }} {{ 1234.5|number_format(1, ".", " ") }} {{ none|default("d") }} {{ "%s!"|format("f") }} {{ "ab"|replace({"a": "b"}) }} {{ "abc"|slice(1, 1) }} {{ "ab"|reverse }} {{ "<i>s</i>"|striptags }} {{ "a b"|url_encode }} {{ "a\nb"|nl2br }} {{ max(1, 3) }}{{ min(1, 3) }}'
            .'{% if none is null and none is none and 2 is even and 1 is odd and [] is empty and [1] is iterable and [1] is sequence and {a: 1} is mapping and true is true and 4 is divisible by(2) and 1 is same as(1) and name is defined %} ok{% endif %}{% set x = 1 %}{% apply upper %}{{ x }}a{% endapply %}';
        $html = $this->engine->render($this->definition('Hi', $body, schema: ['name' => ['type' => 'string'], 'none' => ['type' => 'string']]), ['name' => 'ada lovelace', 'none' => null], $this->context())->html;

        self::assertSame('ADA LOVELACE ada lovelace Ada Lovelace Ada lovelace x a+b 12 2 3 2.6 1 234.5 d f! bb b ba s a%20b a<br />'."\n".'b 31 ok1A', $html);
    }

    public function testTheIntlFiltersAreAllowedWhenInstalled(): void
    {
        $html = $this->engine->render($this->definition('Hi', '{{ 1234.5|format_number(locale="fr") }}|{{ 12|format_currency("CAD", locale="en_CA") }}'), [], $this->context())->html;

        self::assertSame("1\u{202F}234,5|$12.00", $html);
    }

    public function testTheApplicationAddsFiltersThroughItsExtensions(): void
    {
        $extension = new class extends AbstractExtension {
            public function getFilters(): array
            {
                return [new TwigFilter('shout', static fn (string $text): string => $text.'!')];
            }
        };
        $engine = new SandboxedEngine(Theme::create(), null, false, [$extension], ['shout']);

        self::assertSame('hey!', $engine->render($this->definition('Hi', '{{ "hey"|shout }}'), [], $this->context())->html);
        self::assertNotSame([], $this->engine->check($this->definition('Hi', '{{ "hey"|shout }}')));
    }

    public function testUndeclaredVariablesAreReportedAndRefusedAtRunTime(): void
    {
        $definition = $this->definition('Hi {{ name }} {{ email.key }}', '{% set local = 1 %}{{ local }}{% for item in items %}{{ item }}{{ loop.index }}{% endfor %}{{ stranger }}{{ _self }}', schema: ['name' => ['type' => 'string'], 'items' => ['type' => 'list', 'items' => ['type' => 'string']]], text: '{{ other }}');

        self::assertSame(['The template reads "other", which its schema does not declare.', 'The template reads "stranger", which its schema does not declare.'], $this->engine->check($definition));

        $this->expectException(RenderingFailed::class);
        $this->expectExceptionMessageIsOrContains('The body (line 1): Variable "stranger" does not exist.');
        $this->engine->render($definition, ['name' => 'Ada', 'items' => []], $this->context());
    }

    public function testATemplateThatCompilesHasNoError(): void
    {
        self::assertSame([], $this->engine->check($this->definition('Hi {{ name }}', '{{ button("Go", "https://example.com/") }}', schema: ['name' => ['type' => 'string']], preheader: 'Soon', text: 'x')));
    }

    public function testAnOutputLargerThanTheCeilingIsRefused(): void
    {
        $engine = new SandboxedEngine(Theme::create(), maxBytes: 10);

        self::assertSame('0123456789', $engine->render($this->definition('Hi', '0123456789'), [], $this->context())->html);
        $this->expectException(RenderingFailed::class);
        $this->expectExceptionMessageIsOrContains('The body renders more than 0 KiB.');
        $engine->render($this->definition('Hi', '0123456789A'), [], $this->context());
    }

    public function testTheEnvironmentIsBuiltAgainAfterAReset(): void
    {
        $definition = $this->definition('Hi', '<p>x</p>');
        $this->engine->render($definition, [], $this->context());
        $this->engine->reset();

        self::assertSame('<p>x</p>', $this->engine->render($definition, [], $this->context())->html);
        $this->engine->reset();
        self::assertSame([], $this->engine->check($definition));
    }

    public function testTheCompiledTemplatesAreCachedByTheHashOfTheirSource(): void
    {
        $directory = sys_get_temp_dir().'/phil-email-templates-cache-'.bin2hex(random_bytes(4));
        $engine = new SandboxedEngine(Theme::create(), null, $directory);
        $engine->render($this->definition('Hi', '<p>cached</p>'), [], $this->context());

        self::assertDirectoryExists($directory);
        $engine->reset();
        self::assertSame('<p>cached</p>', $engine->render($this->definition('Hi', '<p>cached</p>'), [], $this->context())->html);
    }

    public function testTheLoaderServesItsSourcesByHash(): void
    {
        $loader = new SourceLoader(2);
        $html = $loader->add('a', true);
        $text = $loader->add('a', false);

        self::assertSame(hash('sha256', 'a').'.html', $html);
        self::assertSame(hash('sha256', 'a').'.txt', $text);
        self::assertTrue($loader->exists($html));
        self::assertSame('a', $loader->getSourceContext($html)->getCode());
        self::assertSame($html, $loader->getCacheKey($html));
        self::assertTrue($loader->isFresh($html, 0));
        $loader->add('a', true);
        self::assertTrue($loader->exists($text), 'A source added again is not counted twice.');
        $loader->add('b', true);
        self::assertFalse($loader->exists($html), 'The oldest source is forgotten first.');
        self::assertTrue($loader->exists($text));
        $loader->clear();
        self::assertFalse($loader->exists($text));
        $this->expectExceptionMessageIsOrContains('No template is named "nope".');
        $loader->getSourceContext('nope');
    }

    public function testTheStashKeepsNothingOutsideMarkdown(): void
    {
        $stash = new RenderStash($this->context(), $this->definition('Hi', 'x'));

        self::assertSame('<b>x</b>', $stash->keep('<b>x</b>'));
        self::assertSame('**x**', $stash->markdown('**x**'));
        self::assertSame('kept', $stash->restore('kept'));
        $stash->mode = RenderMode::Markdown;
        self::assertSame('**x**', $stash->markdown('**x**'), 'Without a renderer, Markdown stays as written.');
        $placeholder = $stash->keep('<b>x</b>', false);
        self::assertMatchesRegularExpression('/^phil[0-9a-f]{16}o0z$/', $placeholder);
        self::assertSame("\n\n".substr($placeholder, 0, -2).'1z'."\n\n", $stash->keep('<i>y</i>'));
        self::assertSame('a <b>x</b> <i>y</i> phil'.str_repeat('0', 16).'o0z '.substr($placeholder, 0, -2).'9z', $stash->restore('a '.$placeholder.' '.substr($placeholder, 0, -2).'1z phil'.str_repeat('0', 16).'o0z '.substr($placeholder, 0, -2).'9z'));
        self::assertSame('<b>x</b>', $stash->restore('<p> '.$placeholder.' </p>'));
        self::assertSame('<p>t <b>x</b></p>', $stash->restore('<p>t '.$placeholder.'</p>'));
    }

    public function testTheComponentsNeedTheStash(): void
    {
        $components = new EmailComponents(Theme::create());

        $this->expectExceptionMessageIsOrContains('The email components draw only inside a template rendered by the email templates.');
        $components->button([], 'a', 'https://example.com/');
    }

    /**
     * @param list<string>                        $segments
     * @param array<string, array<string, mixed>> $schema
     */
    private function definition(string $subject, string $body, BodyFormat $format = BodyFormat::Html, array $segments = [], array $schema = [], string $preheader = '', ?string $text = null): TemplateDefinition
    {
        return TemplateDefinition::create($subject, $body, $format, $preheader, $text, VariableSchema::fromArray($schema), $segments);
    }

    /**
     * @param list<string> $segments
     */
    private function context(array $segments = []): RenderContext
    {
        return new RenderContext(TemplateKey::fromString('greeting'), Site::fromString('main'), Locale::fromString('fr_CA'), $segments);
    }
}
