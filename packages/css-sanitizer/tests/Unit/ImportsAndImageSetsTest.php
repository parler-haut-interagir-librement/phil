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
use Phil\CssSanitizer\Exception\InvalidPolicy;
use Phil\CssSanitizer\Policy;
use Phil\CssSanitizer\Removal;
use Phil\CssSanitizer\RemovalReason;
use Phil\CssSanitizer\Sanitized;
use Phil\CssSanitizer\Serializer;
use Phil\CssSanitizer\Sheet\GroupRule;
use Phil\CssSanitizer\Sheet\ImportRule;
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

#[CoversClass(Sanitization::class)]
#[CoversClass(Values::class)]
#[CoversClass(Preludes::class)]
#[CoversClass(Imports::class)]
#[CoversClass(InvalidPolicy::class)]
#[CoversClass(ImportRule::class)]
#[CoversClass(StyleSheet::class)]
#[UsesClass(CssSanitizer::class)]
#[UsesClass(Selectors::class)]
#[UsesClass(FontSources::class)]
#[UsesClass(Sanitized::class)]
#[UsesClass(Removal::class)]
#[UsesClass(RemovalReason::class)]
#[UsesClass(Policy::class)]
#[UsesClass(Parser::class)]
#[UsesClass(Tokenizer::class)]
#[UsesClass(Serializer::class)]
#[UsesClass(StyleRule::class)]
#[UsesClass(GroupRule::class)]
#[UsesClass(RefuseUrls::class)]
#[UsesClass(AllowedFiles::class)]
#[UsesClass(CallbackRewriter::class)]
final class ImportsAndImageSetsTest extends TestCase
{
    public function testItRewritesImportsToAllowedStyleSheets(): void
    {
        $sanitized = new CssSanitizer($this->policy())->sanitize('@import "base.css"; @IMPORT url(print.css) supports(display: grid) print; @import url("./base.css") screen, print; a { color: red }');

        self::assertSame("@import url(\"/m/base.css\");\n@import url(\"/m/print.css\") supports(display: grid) print;\n@import url(\"/m/base.css\") screen, print;\n\na {\n  color: red;\n}\n", $sanitized->css);
        self::assertSame([], $this->removals($sanitized));
        self::assertEquals([new ImportRule('/m/base.css'), new ImportRule('/m/print.css', new Parser()->parseComponentValues('supports(display: grid) print')), new ImportRule('/m/base.css', new Parser()->parseComponentValues('screen, print'))], $sanitized->sheet->imports);
        self::assertSame("@import url(\"/m/base.css\");\n", new CssSanitizer($this->policy())->sanitize('@import "base.css";')->css);
    }

    public function testItRemovesImportsTheRewriterRefuses(): void
    {
        $this->assertSanitized('@import "other.css"; @import url(https://example.com/x.css); @import "javascript:alert(1)"; @import "#top"; @import "a.png"; a { color: red }', "a {\n  color: red;\n}\n", ['url other.css@1', 'url https://example.com/x.css@1', 'dangerous_url javascript:alert(1)@1', 'url #top@1', 'url a.png@1']);
    }

    public function testItRemovesImportsWhoseConditionsAreNotAllowed(): void
    {
        $this->assertSanitized(
            '@import "base.css" layer; @import "base.css" layer(x); @import "base.css" tv; @import "base.css" supports(); @import "base.css" supports(background: url(x)); @import "base.css" supports(display: grid)tv; @import; @import x; @import "base.css" (min-width: 1px) and print;',
            "@import url(\"/m/base.css\") (min-width: 1px) and print;\n",
            ['prelude @import "base.css" layer@1', 'prelude @import "base.css" layer(x)@1', 'prelude @import "base.css" tv@1', 'prelude @import "base.css" supports()@1', 'prelude @import "base.css" supports(background: url("x"))@1', 'prelude @import "base.css" supports(display: grid)tv@1', 'prelude @import@1', 'prelude @import x@1'],
        );
    }

    public function testTheConditionsOfAnImportFollowItsUrlDirectly(): void
    {
        $this->assertSanitized('@import "base.css"print; @import "base.css"tv;', "@import url(\"/m/base.css\") print;\n", ['prelude @import "base.css"tv@1']);
    }

    public function testImportsStandOnlyAtTheStartOfTheSheet(): void
    {
        $this->assertSanitized('a { color: red } @import "base.css"; @media print { @import "base.css"; } @import "base.css" {}', "a {\n  color: red;\n}\n", ['import @import@1', 'import @import@1', 'import @import@1']);
        $this->assertSanitized('@import "base.css" {} @import "base.css";', "@import url(\"/m/base.css\");\n", ['import @import@1']);
        $this->assertSanitized('@media print {} @import "base.css";', '', ['import @import@1']);
    }

    public function testImportsAreRefusedUntilThePolicyAllowsThem(): void
    {
        $this->assertSanitized('@import "base.css";', '', ['import @import@1'], $this->policy()->withoutAtRules(AtRuleKind::Import));
    }

    public function testTheRewriterLearnsWhatEachUrlLoads(): void
    {
        $kinds = [];
        $rewriter = new CallbackRewriter(static function (string $url, UrlKind $kind) use (&$kinds): string {
            $kinds[] = $url.' '.$kind->value;

            return '/m/'.$url;
        });

        new CssSanitizer(new Policy()->withUrlRewriter($rewriter)->withAtRules(AtRuleKind::Import))->sanitize('@import "s.css"; a { background: image-set("i.png" 1x) }');

        self::assertSame(['s.css style-sheet', 'i.png image'], $kinds);
    }

    public function testItRewritesImageSets(): void
    {
        $this->assertSanitized(
            'a { background-image: image-set("a.png" 1x type("image/png"), url(b.png) 2dppx, url("a.png") type("image/avif") 96dpi, linear-gradient(red, blue) 3X); content: -webkit-image-set(\'a.png\' 1x) }',
            "a {\n  background-image: image-set(url(\"/m/a.png\") 1x type(\"image/png\"), url(\"/m/b.png\") 2dppx, url(\"/m/a.png\") type(\"image/avif\") 96dpi, linear-gradient(red, blue) 3X);\n  content: -webkit-image-set(url(\"/m/a.png\") 1x);\n}\n",
            [],
        );
        $this->assertSanitized('a { background: image-set("a.png"1x) }', "a {\n  background: image-set(url(\"/m/a.png\")1x);\n}\n", []);
        $this->assertSanitized('a { background: image-set("a.png" 38dpcm, "#frag") }', "a {\n  background: image-set(url(\"/m/a.png\") 38dpcm, url(\"#frag\"));\n}\n", []);
    }

    public function testItRefusesImageSetsWithAnUrlTheRewriterRefuses(): void
    {
        $this->assertSanitized(
            'a { background: image-set("a.png" 1x, "evil.png" 2x); color: red; list-style-image: image-set("a.png" type("image/png"), "javascript:alert(1)" type("image/png")); background-image: image-set(url(https://example.com/x.png) 1x) }',
            "a {\n  color: red;\n}\n",
            ['url evil.png@1', 'dangerous_url javascript:alert(1)@1', 'url https://example.com/x.png@1'],
        );
    }

    public function testItRefusesImageSetsOutsideTheGrammar(): void
    {
        $this->assertSanitized(
            'a { background: image-set(); background: image-set(red 1x); background: image-set("a.png" 1x 2x); background: image-set("a.png" type("image/png") type("image/gif")); background: image-set("a.png" type(url(b.png))); background: image-set("a.png" type(image/png)); background: image-set("a.png" 1px); background: image-set((a.png) 1x); background: image-set("a.png" 1x, element(#x) 2x); background: image-set("a.png" type("a" "b")); color: red }',
            "a {\n  color: red;\n}\n",
            [
                'value image-set()@1', 'value image-set(red 1x)@1', 'value image-set("a.png" 1x 2x)@1',
                'value image-set("a.png" type("image/png") type("image/gif"))@1', 'value image-set("a.png" type(url("b.png")))@1',
                'value image-set("a.png" type(image/png))@1', 'value image-set("a.png" 1px)@1', 'value image-set((a.png) 1x)@1',
                'function element()@1', 'value image-set("a.png" type("a" "b"))@1',
            ],
        );
    }

    public function testImageSetCannotBeAddedAsAFunction(): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains('The function "image-set()" can run code or load a resource; it is never allowed.');

        new Policy()->withFunctions('image-set');
    }

    private function policy(): Policy
    {
        return new Policy()
            ->withUrlRewriter(new AllowedFiles(['a.png' => '/m/a.png', 'b.png' => '/m/b.png'], [], ['base.css' => '/m/base.css', 'print.css' => '/m/print.css']))
            ->withAtRules(AtRuleKind::Import);
    }

    /**
     * @param list<string> $removals
     */
    private function assertSanitized(string $css, string $expected, array $removals, ?Policy $policy = null): void
    {
        $sanitized = new CssSanitizer($policy ?? $this->policy())->sanitize($css);

        self::assertSame($removals, $this->removals($sanitized));
        self::assertSame($expected, $sanitized->css);
    }

    /**
     * @return list<string>
     */
    private function removals(Sanitized $sanitized): array
    {
        return array_map(static fn (Removal $removal): string => $removal->reason->value.' '.$removal->subject.'@'.$removal->line, $sanitized->removals);
    }
}
