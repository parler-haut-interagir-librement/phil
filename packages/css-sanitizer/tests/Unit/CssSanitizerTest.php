<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Tests\Unit;

use Phil\CssSanitizer\AtRuleKind;
use Phil\CssSanitizer\Check\FontSources;
use Phil\CssSanitizer\Check\Imports;
use Phil\CssSanitizer\Check\Preludes;
use Phil\CssSanitizer\Check\Sanitization;
use Phil\CssSanitizer\Check\Selectors;
use Phil\CssSanitizer\Check\Values;
use Phil\CssSanitizer\CssSanitizer;
use Phil\CssSanitizer\Exception\NestingTooDeep;
use Phil\CssSanitizer\Policy;
use Phil\CssSanitizer\Removal;
use Phil\CssSanitizer\RemovalReason;
use Phil\CssSanitizer\Sanitized;
use Phil\CssSanitizer\Serializer;
use Phil\CssSanitizer\Sheet\GroupRule;
use Phil\CssSanitizer\Sheet\StyleRule;
use Phil\CssSanitizer\Sheet\StyleSheet;
use Phil\CssSanitizer\Syntax\Parser;
use Phil\CssSanitizer\Syntax\Tokenizer;
use Phil\CssSanitizer\Url\AllowedFiles;
use Phil\CssSanitizer\Url\CallbackRewriter;
use Phil\CssSanitizer\Url\RefuseUrls;
use Phil\CssSanitizer\UrlKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CssSanitizer::class)]
#[CoversClass(Sanitization::class)]
#[CoversClass(Values::class)]
#[CoversClass(Preludes::class)]
#[CoversClass(FontSources::class)]
#[CoversClass(Imports::class)]
#[CoversClass(Sanitized::class)]
#[CoversClass(Removal::class)]
#[CoversClass(RemovalReason::class)]
#[UsesClass(Selectors::class)]
#[UsesClass(Policy::class)]
#[UsesClass(Parser::class)]
#[UsesClass(Tokenizer::class)]
#[UsesClass(Serializer::class)]
#[UsesClass(StyleSheet::class)]
#[UsesClass(StyleRule::class)]
#[UsesClass(GroupRule::class)]
#[UsesClass(RefuseUrls::class)]
#[UsesClass(AllowedFiles::class)]
#[UsesClass(CallbackRewriter::class)]
#[UsesClass(NestingTooDeep::class)]
final class CssSanitizerTest extends TestCase
{
    public function testItKeepsWhatThePolicyAllows(): void
    {
        $sanitized = new CssSanitizer()->sanitize("h1 , p > em::before{color:red;margin:0 auto!important}\n\n.a{font:12px/1.5 serif}");

        self::assertTrue($sanitized->isClean());
        self::assertSame("h1, p > em::before {\n  color: red;\n  margin: 0 auto !important;\n}\n\n.a {\n  font: 12px/1.5 serif;\n}\n", $sanitized->css);
        self::assertCount(2, $sanitized->sheet->rules);
        self::assertSame([], $sanitized->declarations);
    }

    public function testItRefusesImports(): void
    {
        $this->assertSanitized('@import url(x.css); @IMPORT "y.css" print; a { color: red }', "a {\n  color: red;\n}\n", ['import @import@1', 'import @IMPORT@1']);
    }

    public function testItRefusesAtRulesThePolicyDoesNotAllow(): void
    {
        $this->assertSanitized("@charset \"utf-8\";\n@namespace x url(y);\n@keyframes k { from { color: red } }\n@layer a;\n@font-feature-values X {}", '', ['at_rule @charset@1', 'at_rule @namespace@2', 'at_rule @keyframes@3', 'at_rule @layer@4', 'at_rule @font-feature-values@5']);

        $policy = new Policy()->withoutAtRules(AtRuleKind::Media, AtRuleKind::Page);

        $this->assertSanitized('@media print { a { color: red } } @page { margin: 0 } @supports (display: grid) { a { color: red } }', "@supports (display: grid) {\n  a {\n    color: red;\n  }\n}\n", ['at_rule @media@1', 'at_rule @page@1'], $policy);
    }

    public function testItRefusesSelectorsOneByOne(): void
    {
        $this->assertSanitized("a, input[value^=\"x\"],\nb:visited, , p { color: red }\ninput[value] { background: red }", "a, p {\n  color: red;\n}\n", ['selector input[value^="x"]@1', 'selector b:visited@1', 'selector @1', 'selector input[value]@3']);
    }

    public function testItDropsRulesLeftEmpty(): void
    {
        $this->assertSanitized('a {} b { position: fixed } c { ; }', '', ['property position@1']);
    }

    public function testItRefusesPropertiesThePolicyDoesNotAllow(): void
    {
        $this->assertSanitized('a { position: absolute; Z-INDEX: 9; cursor: url(x.cur), auto; color: red; COLOR: blue; transition: all 1s }', "a {\n  color: red;\n  COLOR: blue;\n}\n", ['property position@1', 'property z-index@1', 'property cursor@1', 'property transition@1']);
    }

    public function testItRefusesPropertiesThatRunCodeWhateverThePolicy(): void
    {
        $this->assertSanitized('a { behavior: url(x.htc); -moz-binding: url(x.xml#b); -MS-BEHAVIOR: url(y) }', '', ['denied_property behavior@1', 'denied_property -moz-binding@1', 'denied_property -ms-behavior@1']);
    }

    public function testItRefusesFunctionsThePolicyDoesNotAllow(): void
    {
        $this->assertSanitized("a { width: expression(alert(1)); background: image-set('x.png' 1x); color: env(x); content: \"a\" eXpReSsIoN(1); margin: calc(1px + min(2px, 3%)) }", "a {\n  margin: calc(1px + min(2px, 3%));\n}\n", ['function expression()@1', 'url x.png@1', 'function env()@1', 'function eXpReSsIoN()@1']);
    }

    public function testItRefusesEscapedFunctionNames(): void
    {
        $this->assertSanitized('a { width: e\\78 pression(alert(1)); color: \\72 gb(0 0 0) }', "a {\n  color: rgb(0 0 0);\n}\n", ['function expression()@1']);
    }

    public function testItChecksValuesDeeply(): void
    {
        $this->assertSanitized("a {\n color: rgb(var(--x));\n margin: (1px [a] calc(1px * 2 / 3 - 1px + 2px));\n padding: x {y};\n width: 1px: 2px;\n height: @x;\n border: \"a\n;\n}", "a {\n  margin: (1px [a] calc(1px * 2 / 3 - 1px + 2px));\n}\n", ['function var()@2', 'selector padding: x@4', 'syntax y@4', 'value :@5', 'value @x@6', 'value "a"@7']);
    }

    public function testItRefusesOtherDelimsAndEmptyValues(): void
    {
        $this->assertSanitized('a { color: red !x; margin: 1px > 2px; padding: ; width: #; height: <!--; float: left -->; clear: a=b }', '', ['value !@1', 'value >@1', 'value padding@1', 'value #@1', 'value <!--@1', 'value -->@1', 'value =@1']);
    }

    public function testItRefusesTheImportantFlagWhenThePolicySays(): void
    {
        $policy = new Policy()->withImportant(false);

        $this->assertSanitized('a { color: red !important; margin: 0 }', "a {\n  color: red;\n  margin: 0;\n}\n", ['important color@1'], $policy);
        $this->assertSanitized('a { color: red !important }', "a {\n  color: red !important;\n}\n", []);
    }

    public function testItRefusesCustomPropertiesUnlessThePolicyAllowsThem(): void
    {
        $this->assertSanitized('a { --Main: red; color: var(--Main) }', '', ['property --Main@1', 'function var()@1']);

        $policy = new Policy()->withCustomProperties(true);

        $this->assertSanitized('a { --Main: red; color: var(--Main, blue); --bad: url(x) }', "a {\n  --Main: red;\n  color: var(--Main, blue);\n}\n", ['url x@1'], $policy);
    }

    public function testItRewritesUrls(): void
    {
        $policy = new Policy()->withUrlRewriter(new AllowedFiles(['bg.png' => '/files/1/bg.png', 'a b.png' => '/files/1/a%20b.png']));

        $this->assertSanitized("a { background: url(bg.png) no-repeat, URL( \"./bg.png\" ); }\nb { background-image: url('http://evil.example/x.png') }\nc { list-style-image: url(\"a b.png\") }\nd { background: url(other.png) }", "a {\n  background: url(\"/files/1/bg.png\") no-repeat, url(\"/files/1/bg.png\");\n}\n\nc {\n  list-style-image: url(\"/files/1/a%20b.png\");\n}\n", ['url http://evil.example/x.png@2', 'url other.png@4'], $policy);
    }

    public function testItRefusesEveryUrlByDefault(): void
    {
        $this->assertSanitized('a { background: url(bg.png) }', '', ['url bg.png@1']);
    }

    public function testItKeepsFragmentsWithoutAskingTheRewriter(): void
    {
        $asked = [];
        $policy = new Policy()->withUrlRewriter(new CallbackRewriter(static function (string $url, UrlKind $kind) use (&$asked): ?string {
            $asked[] = $url;

            return null;
        }));

        $this->assertSanitized('a { filter: url(#blur); clip-path: url("#c") }', "a {\n  filter: url(\"#blur\");\n  clip-path: url(\"#c\");\n}\n", [], $policy);
        self::assertSame([], $asked);
    }

    public function testItRefusesUrlsThatRunCode(): void
    {
        $policy = new Policy()->withUrlRewriter(new CallbackRewriter(static fn (string $url): string => $url));

        $this->assertSanitized("a { background: url(\"javascript:alert(1)\") }\nb { background: url(\" JaVa\\9 script :x\") }\nc { background: url(vbscript:x) }\nd { background: url(\"livescript:x\") }\ne { background: url(\"mocha:x\") }\nf { background: url(\"java-script:x\") }", "f {\n  background: url(\"java-script:x\");\n}\n", ['dangerous_url javascript:alert(1)@1', "dangerous_url  JaVa\tscript :x@2", 'dangerous_url vbscript:x@3', 'dangerous_url livescript:x@4', 'dangerous_url mocha:x@5'], $policy);
    }

    public function testItRefusesUrlsThatRunCodeBeforeAskingTheRewriter(): void
    {
        $policy = new Policy()->withUrlRewriter(new CallbackRewriter(static fn (string $url): string => '/safe.png'));

        $this->assertSanitized(
            "a { background: url(\"javascript:x\") }\nb { background: url(\"java\\7f script:x\") }\nc { background: url(\"~javascript:x\") }",
            "c {\n  background: url(\"/safe.png\");\n}\n",
            ['dangerous_url javascript:x@1', "dangerous_url java\x7Fscript:x@2"],
            $policy,
        );
    }

    public function testItRefusesUrlsThatRunCodeFromTheRewriter(): void
    {
        $policy = new Policy()->withUrlRewriter(new CallbackRewriter(static fn (string $url): string => 'javascript:'.$url));

        $this->assertSanitized('a { background: url(x) }', '', ['dangerous_url javascript:x@1'], $policy);
    }

    public function testItRefusesMalformedUrls(): void
    {
        $policy = new Policy()->withUrlRewriter(new CallbackRewriter(static fn (string $url): string => $url));

        $this->assertSanitized('a { background: url(a b) } b { background: url("a" "b") } c { background: url(x y) } d { background: url() } e { background: url(f()) }', "d {\n  background: url(\"\");\n}\n", ['value url("")@1', 'value url("a" "b")@1', 'value url("")@1', 'value url("")@1'], $policy);
    }

    public function testItGivesTheKindOfEachUrlToTheRewriter(): void
    {
        $asked = [];
        $policy = new Policy()->withUrlRewriter(new CallbackRewriter(static function (string $url, UrlKind $kind) use (&$asked): string {
            $asked[] = $url.':'.$kind->value;

            return '/'.$url;
        }));

        new CssSanitizer($policy)->sanitize('a { background: url(a.png) } @font-face { font-family: X; src: url(b.woff2) }');

        self::assertSame(['a.png:image', 'b.woff2:font'], $asked);
    }

    public function testItAllowsAttrAndTheUrlTypeOnlyToPointIntoTheDocument(): void
    {
        $this->assertSanitized("a::after { content: target-counter(attr(href url), page) \" \" attr(title) attr(data-n number) counters(item, \".\") }\nb::after { content: TARGET-TEXT( ATTR(href URL) , content) }\nc::after { content: attr(href url) }\nd::after { content: attr(url) }\ne::after { content: attr() }\nf::after { content: attr(x, \"y\") }\ng::after { content: attr(type(<url>)) }\nh::after { content: target-counter(attr(x url), url(\"a\")) }\ni::after { content: target-counter(attr(x \"u\"), page) }\nj::after { content: target-counter( attr(href url) , page) }\nk::after { content: target-counters(url(\"#x\"), list-item, \".\") }", "a::after {\n  content: target-counter(attr(href url), page) \" \" attr(title) attr(data-n number) counters(item, \".\");\n}\n\nb::after {\n  content: TARGET-TEXT(ATTR(href URL) , content);\n}\n\nd::after {\n  content: attr(url);\n}\n\nj::after {\n  content: target-counter(attr(href url) , page);\n}\n\nk::after {\n  content: target-counters(url(\"#x\"), list-item, \".\");\n}\n", ['function attr()@3', 'function attr()@5', 'function attr()@6', 'function attr()@7', 'url a@8', 'function attr()@9']);
    }

    public function testItAllowsTheUrlTypeOfAttrOnlyInTargets(): void
    {
        $this->assertSanitized(
            'a::after { content: counter(attr(x url)) } b::after { content: target-counter(x, page) } c::after { content: target-text(attr(href url), url) }',
            "b::after {\n  content: target-counter(x, page);\n}\n\nc::after {\n  content: target-text(attr(href url), url);\n}\n",
            ['function attr()@1'],
        );
        $this->assertSanitized('a::after { content: target-counter(attr(href url), {x}) }', '', ['value {@1']);
    }

    public function testItRefusesAttrWhenThePolicyDoesNot(): void
    {
        $policy = new Policy()->withoutFunctions('attr');

        $this->assertSanitized('a::after { content: attr(x) } b::after { content: target-counter(attr(href url), page) }', '', ['function attr()@1', 'function attr()@1'], $policy);
        $this->assertSanitized('a::after { content: target-counter(attr(href url), page) }', '', ['function target-counter()@1'], new Policy()->withoutFunctions('target-counter'));
    }

    public function testItChecksMediaQueries(): void
    {
        $this->assertSanitized("@media print { a { color: red } }\n@media only screen and (min-width: 30em), not print and (orientation: landscape) { b { color: red } }\n@media (400px <= width <= 700px) and (aspect-ratio: 16/9) { c { color: red } }\n@media { d { color: red } }\n@media (not (color)) or (min-resolution: 2dppx) { e { color: red } }", "@media print {\n  a {\n    color: red;\n  }\n}\n\n@media only screen and (min-width: 30em), not print and (orientation: landscape) {\n  b {\n    color: red;\n  }\n}\n\n@media (400px <= width <= 700px) and (aspect-ratio: 16/9) {\n  c {\n    color: red;\n  }\n}\n\n@media {\n  d {\n    color: red;\n  }\n}\n\n@media (not (color)) or (min-resolution: 2dppx) {\n  e {\n    color: red;\n  }\n}\n", []);

        $this->assertSanitized("@media speech { a { color: red } }\n@media print, { a { color: red } }\n@media (min-width: url(x)) { a { color: red } }\n@media (min-width: \"x\") { a { color: red } }\n@media (min-width: calc(1px)) { a { color: red } }\n@media [x] { a { color: red } }\n@media print; a { color: red }\n@media (a: #x) { a { color: red } }\n@media (a, b) { a { color: red } }\n@media (a ! b) { a { color: red } }", "a {\n  color: red;\n}\n", ['prelude @media speech@1', 'prelude @media print,@2', 'prelude @media (min-width: url("x"))@3', 'prelude @media (min-width: "x")@4', 'prelude @media (min-width: calc(1px))@5', 'prelude @media [x]@6', 'syntax @media@7', 'prelude @media (a: #x)@8', 'prelude @media (a, b)@9', 'prelude @media (a ! b)@10']);

        $this->assertSanitized('@media screen { a { color: red } } @media PRINT { b { color: red } }', "@media PRINT {\n  b {\n    color: red;\n  }\n}\n", ['prelude @media screen@1'], new Policy()->withMediaTypes('Print'));
    }

    public function testItReadsKeywordsOfPreludesInAnyCase(): void
    {
        self::assertSanitized(
            '@media ONLY Screen AND (min-width: 1px) { a { color: red } } @supports NOT (x: y) { b { color: red } }',
            "@media ONLY Screen AND (min-width: 1px) {\n  a {\n    color: red;\n  }\n}\n\n@supports NOT (x: y) {\n  b {\n    color: red;\n  }\n}\n",
            [],
        );
    }

    public function testItChecksSupportsConditions(): void
    {
        $this->assertSanitized("@supports (display: grid) and (not (display: inline-grid)) { a { display: grid } }\n@supports selector(:is(a)) or (color: rgb(0 0 0)) { b { color: red } }\n@supports (x: [y]) { c { color: red } }", "@supports (display: grid) and (not (display: inline-grid)) {\n  a {\n    display: grid;\n  }\n}\n\n@supports selector(:is(a)) or (color: rgb(0 0 0)) {\n  b {\n    color: red;\n  }\n}\n\n@supports (x: [y]) {\n  c {\n    color: red;\n  }\n}\n", []);

        $this->assertSanitized("@supports { a { color: red } }\n@supports (background: url(x)) { a { color: red } }\n@supports (background: url(\"x\")) { a { color: red } }\n@supports (x: \"a\n) { a { color: red } }\n@supports (x: y;) { a { color: red } }\n@supports (x: {y}) { a { color: red } }\n@supports x { a { color: red } }\n@supports f(x) { a { color: red } }\n@supports [x] { a { color: red } }\n@supports (x: url(a b)) { a { color: red } }\n@supports (x: f(url(a))) { a { color: red } }", '', ['prelude @supports@1', 'prelude @supports (background: url("x"))@2', 'prelude @supports (background: url("x"))@3', 'prelude @supports (x: "a" )@4', 'prelude @supports (x: y;)@6', 'prelude @supports (x: {y})@7', 'prelude @supports x@8', 'prelude @supports f(x)@9', 'prelude @supports [x]@10', 'prelude @supports (x: url(""))@11', 'prelude @supports (x: f(url("a")))@12']);
    }

    public function testItSanitizesNestedGroupRules(): void
    {
        $this->assertSanitized('@media print { @supports (display: grid) { a { display: grid; position: fixed } } @import "x"; @page { margin: 1cm } b {} }', "@media print {\n  @supports (display: grid) {\n    a {\n      display: grid;\n    }\n  }\n\n  @page {\n    margin: 1cm;\n  }\n}\n", ['property position@1', 'import @import@1']);
        $this->assertSanitized('@media print { b { position: fixed } }', '', ['property position@1']);
    }

    public function testItChecksPageRules(): void
    {
        $this->assertSanitized("@page { size: A4 landscape; margin: 2cm; marks: crop cross; bleed: 3mm; page-orientation: rotate-left; position: fixed }\n@page :first { margin-top: 5cm }\n@page chapter:left, :RIGHT, :blank, index { color: red }", "@page {\n  size: A4 landscape;\n  margin: 2cm;\n  marks: crop cross;\n  bleed: 3mm;\n  page-orientation: rotate-left;\n}\n\n@page :first {\n  margin-top: 5cm;\n}\n\n@page chapter:left, :RIGHT, :blank, index {\n  color: red;\n}\n", ['property position@1']);

        $this->assertSanitized("@page :nth(1) { margin: 0 }\n@page : first { margin: 0 }\n@page a b { margin: 0 }\n@page :first:hover { margin: 0 }\n@page , a { margin: 0 }\n@page 1 { margin: 0 }\n@page :first : { margin: 0 }\n@page :\"x\" { margin: 0 }", '', ['prelude @page :nth(1)@1', 'prelude @page : first@2', 'prelude @page a b@3', 'prelude @page :first:hover@4', 'prelude @page , a@5', 'prelude @page 1@6', 'prelude @page :first :@7', 'prelude @page :"x"@8']);

        $this->assertSanitized('@page a:first:left { margin: 0 }', "@page a:first:left {\n  margin: 0;\n}\n", []);
        $this->assertSanitized('@page (x)first { margin: 0 }', '', ['prelude @page (x)first@1']);
        $this->assertSanitized('a { size: A4 } @page { size: A4 }', "@page {\n  size: A4;\n}\n", ['property size@1']);
    }

    public function testItKeepsListsOfDeclarations(): void
    {
        $policy = new Policy()->withUrlRewriter(new AllowedFiles(fonts: ['a.woff2' => '/a.woff2']));
        $sanitized = new CssSanitizer($policy)->sanitize('@page { position: x; margin: 0; @top-center { position: x; content: "a" } } @font-face { color: red; font-family: A; src: url(a.woff2) } a { position: x; color: red }');
        [$page, $font, $style] = $sanitized->sheet->rules;

        self::assertInstanceOf(GroupRule::class, $page);
        self::assertSame([0], array_keys($page->declarations));
        self::assertInstanceOf(GroupRule::class, $page->rules[0]);
        self::assertSame([0], array_keys($page->rules[0]->declarations));
        self::assertInstanceOf(GroupRule::class, $font);
        self::assertSame([0, 1], array_keys($font->declarations));
        self::assertInstanceOf(StyleRule::class, $style);
        self::assertSame([0], array_keys($style->declarations));
    }

    public function testItChecksMarginBoxes(): void
    {
        $this->assertSanitized("@page {\n  @top-center { content: string(title); position: fixed; @x {} ; nope }\n  @BOTTOM-RIGHT { content: counter(page) \" / \" counter(pages); size: A5 }\n  @top-left x { content: \"a\" }\n  @top-left;\n  @foo { content: \"a\" }\n  @left-middle { position: fixed }\n  bad stuff;\n}", "@page {\n  @top-center {\n    content: string(title);\n  }\n\n  @bottom-right {\n    content: counter(page) \" / \" counter(pages);\n    size: A5;\n  }\n}\n", ['property position@2', 'at_rule @x@2', 'syntax nope@2', 'at_rule @top-left@4', 'at_rule @top-left@5', 'at_rule @foo@6', 'property position@7', 'syntax bad stuff@8']);

        $boxes = ['top-left-corner', 'top-left', 'top-center', 'top-right', 'top-right-corner', 'bottom-left-corner', 'bottom-left', 'bottom-center', 'bottom-right', 'bottom-right-corner', 'left-top', 'left-middle', 'left-bottom', 'right-top', 'right-middle', 'right-bottom'];
        $sanitized = new CssSanitizer()->sanitize('@page { '.implode(' ', array_map(static fn (string $box): string => '@'.$box.' { content: "x" }', $boxes)).' }');

        self::assertTrue($sanitized->isClean());
        self::assertInstanceOf(GroupRule::class, $sanitized->sheet->rules[0]);
        self::assertSame($boxes, array_map(static fn (StyleRule|GroupRule $rule): string => $rule instanceof GroupRule ? $rule->name : '', $sanitized->sheet->rules[0]->rules));
    }

    public function testItChecksFontFaces(): void
    {
        $policy = new Policy()->withUrlRewriter(new AllowedFiles(fonts: ['a.woff2' => '/fonts/a.woff2', 'b.woff' => '/fonts/b.woff']));

        $this->assertSanitized("@font-face {\n  font-family: \"A\";\n  src: local(\"A Regular\"), local(A-Regular), url(a.woff2) format(\"woff2\") tech(variations), url(\"./b.woff\") format(woff), url(http://evil/x.woff);\n  font-weight: 100 900;\n  font-display: swap;\n  unicode-range: U+0000-00FF;\n  color: red;\n  @x {}\n  junk\n}", "@font-face {\n  font-family: \"A\";\n  src: local(\"A Regular\"), local(A-Regular), url(\"/fonts/a.woff2\") format(\"woff2\") tech(variations), url(\"/fonts/b.woff\") format(woff);\n  font-weight: 100 900;\n  font-display: swap;\n  unicode-range: U+0000-00FF;\n}\n", ['url http://evil/x.woff@3', 'property color@7', 'at_rule @x@8', 'syntax junk@9'], $policy);
    }

    public function testItRefusesFontSourcesItCannotUse(): void
    {
        $policy = new Policy()->withUrlRewriter(new CallbackRewriter(static fn (string $url): string => '/f/'.$url));

        $this->assertSanitized('@font-face { font-family: A; src: local(), local(1px), local(A) format("x"), url(a) x, url(a) format(url(b)), format("x"), url(a) format(1px), "a", url(a) local(b), url(ok) }', "@font-face {\n  font-family: A;\n  src: url(\"/f/ok\");\n}\n", ['font_source local()@1', 'font_source local(1px)@1', 'font_source local(A) format("x")@1', 'font_source url("a") x@1', 'font_source url("a") format(url("b"))@1', 'font_source format("x")@1', 'font_source url("a") format(1px)@1', 'font_source "a"@1', 'font_source url("a") local(b)@1'], $policy);
    }

    public function testItRefusesFontFacesWithoutFamilyOrSource(): void
    {
        $this->assertSanitized("@font-face { font-family: A; src: url(x) }\n@font-face { src: local(A) }\n@font-face { font-family: A }\n@font-face x { font-family: A; src: local(A) }\n@font-face { font-family: A; src: , }", '', ['url x@1', 'font_source @font-face@1', 'font_source @font-face@2', 'font_source @font-face@3', 'prelude @font-face x@4', 'font_source @5', 'font_source @5', 'font_source @font-face@5']);

        $this->assertSanitized('@font-face { font-family: A; FONT-FAMILY: B; src: local(A); SRC: local(B) }', "@font-face {\n  FONT-FAMILY: B;\n  src: local(B);\n}\n", []);
    }

    public function testItReportsWhatCssDropsAsParseErrors(): void
    {
        $this->assertSanitized("a { color red; ; 1px: x; color: blue }\nb", "a {\n  color: blue;\n}\n", ['syntax color red@1', 'syntax 1px: x@1', 'syntax b@2']);
    }

    public function testItRefusesAtRulesInsideStyleRules(): void
    {
        $this->assertSanitized('a { color: red; @font-face { font-family: x } @page { margin: 0 } }', "a {\n  color: red;\n}\n", ['at_rule @font-face@1', 'at_rule @page@1']);
        $this->assertSanitized('a { color: red; @media print { color: blue } }', "a {\n  color: red;\n}\n", ['nesting @media@1'], new Policy()->withMaxNesting(0));
    }

    public function testItScopesStyleRules(): void
    {
        $policy = new Policy()->withScope('.user-styles');

        $this->assertSanitized('h1, p > em { color: red } @media print { a { color: red } } @page { margin: 0 }', ".user-styles h1, .user-styles p > em {\n  color: red;\n}\n\n@media print {\n  .user-styles a {\n    color: red;\n  }\n}\n\n@page {\n  margin: 0;\n}\n", [], $policy);
        self::assertSame("a {\n  color: red;\n}\n", new CssSanitizer($policy->withScope(null))->sanitize('a { color: red }')->css);
    }

    /**
     * A source of a megabyte can hold hundreds of thousands of refused rules: the first hundred removals are listed,
     * and the others only counted.
     */
    public function testItListsTheFirstHundredRemovalsAndCountsTheOthers(): void
    {
        $sanitizer = new CssSanitizer();

        $exact = $sanitizer->sanitize(str_repeat('a{x:1}', 100));
        self::assertCount(100, $exact->removals);
        self::assertSame(0, $exact->omittedRemovals);

        $many = $sanitizer->sanitize("a{x:1}\n".str_repeat('a{x:1}', 149));
        self::assertCount(Sanitized::MAX_REMOVALS, $many->removals);
        self::assertSame(50, $many->omittedRemovals);
        self::assertSame(['property x@1', 'property x@2'], \array_slice($this->removals($many), 0, 2));
        self::assertFalse($many->isClean());

        $values = $sanitizer->sanitizeDeclarations(str_repeat('x: 1; ', 99).str_repeat('color: url(x.png); ', 3));
        self::assertSame(['property x@1', 'url x.png@1'], array_values(array_unique($this->removals($values))));
        self::assertSame(2, $values->omittedRemovals);

        $fonts = $sanitizer->sanitize(str_repeat('a{x:1}', 99).str_repeat('@font-face { font-family: f; src: url(a.woff), url(b.woff) } ', 2));
        self::assertSame('url a.woff@1', $this->removals($fonts)[99]);
        self::assertSame(5, $fonts->omittedRemovals, 'two sources and a rule without source each time, less the one listed');

        self::assertSame(0, new Sanitized('')->omittedRemovals);
        self::assertSame(100, Sanitized::MAX_REMOVALS);
    }

    public function testItRefusesSourcesTooLongWhole(): void
    {
        $policy = new Policy()->withMaxLength(20);

        $this->assertSanitized('a { color: red; }   ', "a {\n  color: red;\n}\n", [], $policy);
        $this->assertSanitized('a { color: red; }    ', '', ['too_long 21 bytes@1'], $policy);
        self::assertSame(['too_long 21 bytes@1'], $this->removals(new CssSanitizer($policy)->sanitizeDeclarations('color: red; margin: 0')));
        self::assertSame('color: red; margin: 1', new CssSanitizer($policy)->sanitizeDeclarations('color: red; margin:1')->css);
    }

    public function testItRefusesSourcesNestedTooDeepWhole(): void
    {
        $policy = new Policy()->withMaxDepth(3);

        $this->assertSanitized('a { margin: calc((1px)) }', "a {\n  margin: calc((1px));\n}\n", [], $policy);
        $this->assertSanitized('a { margin: calc(((1px))) }', '', ['too_deep 27 bytes@1'], $policy);
        self::assertSame(['too_deep 23 bytes@1'], $this->removals(new CssSanitizer($policy)->sanitizeDeclarations('margin: calc((((1px))))')));
        self::assertSame([], $this->removals(new CssSanitizer($policy)->sanitizeDeclarations('margin: calc((1px))')));
    }

    public function testItNestsThirtyTwoLevelsByDefault(): void
    {
        $sanitizer = new CssSanitizer();

        self::assertTrue($sanitizer->sanitize('a { margin: '.str_repeat('(', 31).'1px'.str_repeat(')', 31).' }')->isClean());
        self::assertFalse($sanitizer->sanitize('a { margin: '.str_repeat('(', 32).'1px'.str_repeat(')', 32).' }')->isClean());
    }

    public function testItSanitizesStyleAttributes(): void
    {
        $policy = new Policy()->withUrlRewriter(new AllowedFiles(['bg.png' => '/files/bg.png']));
        $sanitized = new CssSanitizer($policy)->sanitizeDeclarations("color: red; position: absolute;\nbackground: url(bg.png) !important; @media print {} ; junk; width: expression(1)");

        self::assertSame('color: red; background: url("/files/bg.png") !important', $sanitized->css);
        self::assertSame(['property position@1', 'at_rule @media@2', 'syntax junk@2', 'function expression()@2'], $this->removals($sanitized));
        self::assertCount(2, $sanitized->declarations);
        self::assertSame([0, 1], array_keys($sanitized->declarations));
        self::assertSame([], $sanitized->sheet->rules);
        self::assertSame('', new CssSanitizer()->sanitizeDeclarations('')->css);
    }

    public function testItCanChangePolicy(): void
    {
        $sanitizer = new CssSanitizer();
        $relaxed = $sanitizer->withPolicy(new Policy()->withProperties('position'));

        self::assertFalse($sanitizer->sanitize('a { position: relative }')->isClean());
        self::assertTrue($relaxed->sanitize('a { position: relative }')->isClean());
    }

    public function testItNeverWritesWhatCouldCloseTheStyleElement(): void
    {
        $policy = new Policy()->withUrlRewriter(new CallbackRewriter(static fn (string $url): string => $url))->withScope('.x');
        $sanitized = new CssSanitizer($policy)->sanitize(
            "a::after { content: \"</style><script>alert(1)</script>\" }\n"
            ."b { background: url(\"</style>\") }\n"
            ."@media (width</style><script>) { c { color: red } }\n"
            ."@supports (x: a</style>) { d { color: red } }\n"
            ."\\3c/style\\3e { color: red }\n"
            .'e { font-family: \\3c/style\\3e }',
        );

        self::assertDoesNotMatchRegularExpression('~</[a-z]~i', $sanitized->css);
        self::assertStringContainsString('content: "\\3c /style>\\3c script>alert(1)\\3c /script>";', $sanitized->css);
        self::assertStringContainsString('@supports (x: a</**//style>)', $sanitized->css);
        self::assertStringContainsString('font-family: \\3c /style\\>;', $sanitized->css);
        self::assertStringContainsString('@media (width</**//style><script>)', $sanitized->css);
        self::assertSame(['selector \\3c /style\\>@5'], self::removals($sanitized));
    }

    public function testRemovalsCutLongSubjects(): void
    {
        $removal = new Removal(RemovalReason::Selector, 'a'.str_repeat('é', 80), 3);

        self::assertSame('a'.str_repeat('é', 79).'…', $removal->subject);
        self::assertSame(str_repeat('é', 80), new Removal(RemovalReason::Selector, str_repeat('é', 80))->subject);
        self::assertSame(1, new Removal(RemovalReason::Url, 'x')->line);
        self::assertSame(3, $removal->line);
    }

    public function testReasonsDescribeThemselves(): void
    {
        $descriptions = array_map(static fn (RemovalReason $reason): string => $reason->describe(), RemovalReason::cases());

        self::assertSame([
            'an @import rule', 'an at-rule that is not allowed', 'a media query, condition or page selector that is not allowed',
            'a selector that is not allowed', 'a property that is not allowed', 'a property that runs code',
            'a function that is not allowed', 'a URL that is not allowed', 'a URL that runs code', 'a value that is not allowed',
            'an !important flag', 'text that is not valid CSS', 'a font source that cannot be used',
            'a style sheet that is too long', 'blocks nested too deep', 'a rule nested deeper than allowed',
        ], $descriptions);
    }

    public function testAResultWithoutRemovalsIsClean(): void
    {
        self::assertTrue(new Sanitized('')->isClean());
        self::assertFalse(new Sanitized('', [new Removal(RemovalReason::Url, 'x')])->isClean());
    }

    /**
     * @param list<string> $removals
     */
    private function assertSanitized(string $css, string $expected, array $removals, Policy $policy = new Policy()): void
    {
        $sanitized = new CssSanitizer($policy)->sanitize($css);

        self::assertSame($expected, $sanitized->css);
        self::assertSame($removals, $this->removals($sanitized));
    }

    /**
     * @return list<string>
     */
    private function removals(Sanitized $sanitized): array
    {
        return array_map(static fn (Removal $removal): string => $removal->reason->value.' '.$removal->subject.'@'.$removal->line, $sanitized->removals);
    }
}
