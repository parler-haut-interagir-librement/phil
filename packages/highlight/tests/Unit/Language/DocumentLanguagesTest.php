<?php

declare(strict_types=1);

namespace Phil\Highlight\Tests\Unit\Language;

use Phil\Highlight\Language;
use Phil\Highlight\Language\Code;
use Phil\Highlight\Language\Definitions;
use Phil\Highlight\Language\Markdown;
use Phil\Highlight\Language\Markup;
use Phil\Highlight\Language\Php;
use Phil\Highlight\Language\StyleSheet;
use Phil\Highlight\Language\Twig;
use Phil\Highlight\Tests\Unit\Tokens;
use Phil\Highlight\Token;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Markdown::class)]
#[CoversClass(Twig::class)]
#[CoversClass(Php::class)]
final class DocumentLanguagesTest extends TestCase
{
    public function testTheyHaveTheirNames(): void
    {
        self::assertSame(['markdown', 'md', 'mkd', 'mdx', 'commonmark', 'gfm'], [$this->language('markdown')->name(), ...$this->language('markdown')->aliases()]);
        self::assertSame(['twig', 'html.twig', 'jinja', 'jinja2', 'nunjucks', 'njk', 'liquid'], [$this->language('twig')->name(), ...$this->language('twig')->aliases()]);
        self::assertSame(['php', 'php8', 'phtml'], [$this->language('php')->name(), ...$this->language('php')->aliases()]);
    }

    private function language(string $name): Language
    {
        $html = Markup::html(new Code(Definitions::javascript()), new StyleSheet());

        return match ($name) {
            'markdown' => new Markdown(),
            'twig' => new Twig($html, new Code(Definitions::twigExpression())),
            default => new Php(new Code(Definitions::php()), $html),
        };
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function sources(): iterable
    {
        yield 'markdown: headings and breaks' => ['markdown', "# A\n###### B\n####### C\n#D\n#\n---\n* * *\n___\n===\n--\n-=-",
            "{heading:# A}\n{heading:###### B}\n####### C\n#D\n{heading:#}\n{punctuation:---}\n{punctuation:* * *}\n{punctuation:___}\n{punctuation:===}\n--\n-=-"];
        yield 'markdown: fenced blocks' => ['markdown', "```php\necho 1;\n````\n```\n~~~~ js\nx\n  ~~~~~ \ny\n```",
            "{punctuation:```}{meta:php}\n{code:echo 1;}\n{punctuation:````}\n{punctuation:```}\n{code:~~~~ js}\n{code:x}\n  {code:~~~~~ }\n{code:y}\n{punctuation:```}"];
        yield 'markdown: a fence open to the end' => ['markdown', "```\na",
            "{punctuation:```}\n{code:a}"];
        yield 'markdown: quotes, lists and tasks' => ['markdown', "> a\n>> b\n- c\n* d\n+ e\n1. f\n10) g\n- [ ] h\n- [x] i\n- [X] j\n-k\n1.l\n123456789. m\n1234567890. n\n-\n- [ ]x",
            "{punctuation:>} a\n{punctuation:>>} b\n{punctuation:-} c\n{punctuation:*} d\n{punctuation:+} e\n{punctuation:1.} f\n{punctuation:10)} g\n{punctuation:-} {punctuation:[ ]} h\n{punctuation:-} {punctuation:[x]} i\n{punctuation:-} {punctuation:[X]} j\n-k\n1.l\n{punctuation:123456789.} m\n1234567890. n\n-\n{punctuation:-} [ ]x"];
        yield 'markdown: inline marks' => ['markdown', 'a **b** __c__ *d* _e_ `f` ``g`h`` \\*i* snake_case_name ** * `` ',
            'a {strong:**b**} {strong:__c__} {emphasis:*d*} {emphasis:_e_} {code:`f`} {code:``g`h``} \\*i* snake_case_name ** * `` '];
        yield 'markdown: links and images' => ['markdown', '[a](b) [c][d] ![e](f) [g] h] [i](j <https://k> <l@m> <n> <o p@q> <http://r s> <t',
            '{link:[a](b)} {link:[c][d]} {link:![e](f)} [g] h] [i](j {link:<https://k>} {link:<l@m>} <n> <o p@q> <http://r s> <t'];
        yield 'markdown: marks that do not close on the line' => ['markdown', "**a\nb** *c\n[d\n](e) `f\ng`",
            "**a\nb** *c\n[d\n](e) `f\ng`"];
        yield 'markdown: a lone bang and underscores' => ['markdown', '! _a _b_',
            '! {emphasis:_a _}b_'];
        yield 'markdown: emphasis that opens before a space' => ['markdown', 'a * b * c ** d **',
            'a * b * c ** d **'];
        yield 'markdown: an escape at the end' => ['markdown', 'a\\',
            'a\\'];
        yield 'markdown: indented lines' => ['markdown', "   # a\n  - b",
            "   {heading:# a}\n  {punctuation:-} b"];
        yield 'markdown: a tab after a list mark' => ['markdown', "-\tb",
            "{punctuation:-}\tb"];
        yield 'twig: prints, tags and comments' => ['twig', '<p>{{ a|upper }}</p>{% if b -%}c{%- endif %}{#d#}{{~ e ~}}',
            '{punctuation:<}{tag:p}{punctuation:>{{} a{operator:|}{function:upper} {punctuation:}}</}{tag:p}{punctuation:>{%} {keyword:if} b {punctuation:-%}}c{punctuation:{%-} {keyword:endif} {punctuation:%}}{comment:{#d#}}{punctuation:{{~} e {punctuation:~}}}'];
        yield 'twig: a cut tag' => ['twig', '<a href="{{ u }}">x</a>',
            '{punctuation:<}{tag:a} {attr-name:href}{punctuation:=}{string:"}{punctuation:{{} u {punctuation:}}}">x{punctuation:</}{tag:a}{punctuation:>}'];
        yield 'twig: braces that are not delimiters' => ['twig', 'a { b {c} d',
            'a { b {c} d'];
        yield 'twig: a delimiter open to the end' => ['twig', '{{ a',
            '{punctuation:{{} a'];
        yield 'twig: a comment open to the end' => ['twig', '{# a',
            '{comment:{# a}'];
        yield 'twig: a delimiter at the end' => ['twig', 'a {',
            'a {'];
        yield 'php: code only' => ['php', 'echo $a; ?>',
            '{keyword:echo} {variable:$a}{punctuation:;} {meta:?>}'];
        yield 'php: markup and code' => ['php', "<h1><?= \$t ?></h1>\n<?php if (\$x): ?>\n<p>y</p>\n<?php endif;",
            "{punctuation:<}{tag:h1}{punctuation:>}{meta:<?=} {variable:\$t} {meta:?>}{punctuation:</}{tag:h1}{punctuation:>}\n{meta:<?php} {keyword:if} {punctuation:(}{variable:\$x}{punctuation:)}{operator::} {meta:?>}\n{punctuation:<}{tag:p}{punctuation:>}y{punctuation:</}{tag:p}{punctuation:>}\n{meta:<?php} {keyword:endif}{punctuation:;}"];
        yield 'php: an opening tag first' => ['php', '<?php echo 1;',
            '{meta:<?php} {keyword:echo} {number:1}{punctuation:;}'];
        yield 'php: the short tag before the long one' => ['php', 'a<?= 1 ?>b<?php 2 ?>c',
            'a{meta:<?=} {number:1} {meta:?>}b{meta:<?php} {number:2} {meta:?>}c'];
        yield 'markdown: a fenced block with an empty line and a spaced fence' => ['markdown', "```\nx\n\n```  \ny",
            "{punctuation:```}\n{code:x}\n\n{punctuation:```  }\ny"];
        yield 'markdown: a tab first' => ['markdown', "\tx\n\t[ ] y",
            "\tx\n\t[ ] y"];
        yield 'markdown: a list in a quote' => ['markdown', '> - a',
            '{punctuation:>} {punctuation:-} a'];
        yield 'markdown: marks that are not lists' => ['markdown', ". x\n1  x",
            ". x\n1  x"];
        yield 'markdown: marks after an escape' => ['markdown', '\\**b**',
            '\\*{emphasis:*b*}*'];
        yield 'markdown: underscores at the start and after a word' => ['markdown', "_a_\nx _y_\nz_w_",
            "{emphasis:_a_}\nx {emphasis:_y_}\nz_w_"];
        yield 'markdown: a bang before emphasis' => ['markdown', '!*a*',
            '!{emphasis:*a*}'];
        yield 'markdown: marks with nothing between' => ['markdown', "a ****\n[](x)\n[a][]\n[a][b]c",
            "a ****\n{link:[](x)}\n{link:[a][]}\n{link:[a][b]}c"];
        yield 'markdown: lone brackets before marks' => ['markdown', "[(x)\n[*a*](b\n[*b*\n<*a*",
            "[(x)\n[{emphasis:*a*}](b\n[{emphasis:*b*}\n<{emphasis:*a*}"];
        yield 'markdown: an autolink first' => ['markdown', '<a@b>',
            '{link:<a@b>}'];
        yield 'markdown: a backtick at the end' => ['markdown', 'x `',
            'x `'];
        yield 'twig: three braces' => ['twig', '{{{ a }}',
            '{punctuation:{{{} a {punctuation:}}}'];
        yield 'php: the short tag alone' => ['php', '<p><?= 1 ?></p>',
            '{punctuation:<}{tag:p}{punctuation:>}{meta:<?=} {number:1} {meta:?>}{punctuation:</}{tag:p}{punctuation:>}'];
        yield 'php: the long tag alone' => ['php', '<?php 1 ?><p>',
            '{meta:<?php} {number:1} {meta:?>}{punctuation:<}{tag:p}{punctuation:>}'];
    }

    #[DataProvider('sources')]
    public function testItTokenizes(string $language, string $source, string $expected): void
    {
        $tokens = $this->language($language)->tokenize($source);

        self::assertSame($expected, Tokens::render($tokens));
        self::assertSame($source, implode('', array_map(static fn (Token $token): string => $token->text, $tokens)));
    }
}
