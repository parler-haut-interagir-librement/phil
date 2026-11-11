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
use Phil\CssSanitizer\Url\RefuseUrls;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Sanitization::class)]
#[CoversClass(Selectors::class)]
#[CoversClass(StyleRule::class)]
#[UsesClass(CssSanitizer::class)]
#[UsesClass(Values::class)]
#[UsesClass(Preludes::class)]
#[UsesClass(Imports::class)]
#[UsesClass(FontSources::class)]
#[UsesClass(Sanitized::class)]
#[UsesClass(Removal::class)]
#[UsesClass(RemovalReason::class)]
#[UsesClass(Policy::class)]
#[UsesClass(Parser::class)]
#[UsesClass(Tokenizer::class)]
#[UsesClass(Serializer::class)]
#[UsesClass(StyleSheet::class)]
#[UsesClass(GroupRule::class)]
#[UsesClass(RefuseUrls::class)]
#[UsesClass(NestingTooDeep::class)]
final class NestingTest extends TestCase
{
    public function testItKeepsNestedRules(): void
    {
        $this->assertSanitized(
            'article { color: red; & h2 { margin: 0 } > p { padding: 0 } &:hover { color: blue } .x & { color: green } p&.y { margin: 1px } &::before { content: "x" } }',
            "article {\n  color: red;\n\n  & h2 {\n    margin: 0;\n  }\n\n  > p {\n    padding: 0;\n  }\n\n  &:hover {\n    color: blue;\n  }\n\n  .x & {\n    color: green;\n  }\n\n  p&.y {\n    margin: 1px;\n  }\n\n  &::before {\n    content: \"x\";\n  }\n}\n",
            [],
        );
    }

    public function testItWritesDeclarationsBeforeNestedRules(): void
    {
        $this->assertSanitized('a { & b { color: red } color: blue; }', "a {\n  color: blue;\n\n  & b {\n    color: red;\n  }\n}\n", []);
    }

    public function testItReadsAmbiguousItemsAsCssNestingDoes(): void
    {
        $this->assertSanitized('a { b:hover { color: red } margin: 0; em { padding: 0 } }', "a {\n  margin: 0;\n\n  b:hover {\n    color: red;\n  }\n\n  em {\n    padding: 0;\n  }\n}\n", []);
        $this->assertSanitized('a { & b; color: red }', "a {\n  color: red;\n}\n", ['syntax & b@1']);
        $this->assertSanitized('a { color: {red} }', '', ['value {@1']);
        $this->assertSanitized('a { --x: {red} blue }', '', ['property --x@1']);
        $this->assertSanitized('a { & b }', '', ['syntax & b@1']);
    }

    public function testItScopesTopLevelSelectorsOnly(): void
    {
        $policy = new Policy()->withScope('.user');

        $this->assertSanitized('p { & em { color: red } }', ".user p {\n  & em {\n    color: red;\n  }\n}\n", [], $policy);
        $this->assertSanitized('@media print { p { em { color: red } } }', "@media print {\n  .user p {\n    em {\n      color: red;\n    }\n  }\n}\n", [], $policy);
    }

    public function testTheNestingSelectorCannotLeaveTheScope(): void
    {
        $policy = new Policy()->withScope('.user');

        $this->assertSanitized(
            'p { :not(&) { color: red } :is(&) { color: red } :where(a, :not(&)) { color: red } :not(& a) { color: red } & + & { color: blue } }',
            ".user p {\n  & + & {\n    color: blue;\n  }\n}\n",
            ['selector :not(&)@1', 'selector :is(&)@1', 'selector :where(a, :not(&))@1', 'selector :not(& a)@1'],
            $policy,
        );
        $this->assertSanitized('& { color: red } & p { color: red } p, & { color: blue }', ".user p {\n  color: blue;\n}\n", ['selector &@1', 'selector & p@1', 'selector &@1'], $policy);
    }

    public function testItRefusesNestedSelectorsOutsideTheGrammar(): void
    {
        $this->assertSanitized(
            'a { &div { color: red } > { color: red } input[value] { color: red } ::before& { color: red } > > b { color: red } b, &.ok { color: blue } }',
            "a {\n  b, &.ok {\n    color: blue;\n  }\n}\n",
            ['selector &div@1', 'selector >@1', 'selector input[value]@1', 'selector ::before&@1', 'selector > > b@1'],
        );
    }

    public function testItBoundsNestingByThePolicy(): void
    {
        $css = 'a { b { c { d { e { color: red } } } } }';

        $this->assertSanitized($css, '', ['nesting e@1']);
        $this->assertSanitized('a { b { c { d { color: red } } } }', "a {\n  b {\n    c {\n      d {\n        color: red;\n      }\n    }\n  }\n}\n", []);
        $this->assertSanitized($css, '', ['nesting b@1'], new Policy()->withMaxNesting(0));
        $this->assertSanitized('a { color: red; b { c { color: blue } } }', "a {\n  color: red;\n\n  b {\n    c {\n      color: blue;\n    }\n  }\n}\n", [], new Policy()->withMaxNesting(2));
        $this->assertSanitized('a { color: red; b { c { color: blue } } }', "a {\n  color: red;\n}\n", ['nesting c@1'], new Policy()->withMaxNesting(1));
        $this->assertSanitized('a { color: red; @media print { b { color: blue } } }', "a {\n  color: red;\n}\n", ['nesting b@1'], new Policy()->withMaxNesting(1));
        $this->assertSanitized('a { @media print { b { color: blue } } }', "a {\n  @media print {\n    b {\n      color: blue;\n    }\n  }\n}\n", [], new Policy()->withMaxNesting(2));
        $this->assertSanitized('a { >b { color: blue } }', "a {\n  >b {\n    color: blue;\n  }\n}\n", []);
    }

    public function testDeepNestingIsRefusedWholeByTheDepthLimit(): void
    {
        $css = str_repeat('a { ', 200).str_repeat('} ', 200);
        $sanitized = new CssSanitizer(new Policy()->withMaxNesting(1000))->sanitize($css);

        self::assertSame('', $sanitized->css);
        self::assertSame(['too_deep '.\strlen($css).' bytes@1'], $this->removals($sanitized));
    }

    public function testItKeepsNestedMediaAndSupportsRules(): void
    {
        $this->assertSanitized(
            'a { color: red; @media print { color: black; & b { color: gray } } @supports (display: grid) { display: grid } }',
            "a {\n  color: red;\n\n  @media print {\n    color: black;\n\n    & b {\n      color: gray;\n    }\n  }\n\n  @supports (display: grid) {\n    display: grid;\n  }\n}\n",
            [],
        );
        $this->assertSanitized('a { @media print { @supports (display: grid) { display: grid } } }', "a {\n  @media print {\n    @supports (display: grid) {\n      display: grid;\n    }\n  }\n}\n", []);
    }

    public function testItChecksNestedMediaAndSupportsRules(): void
    {
        $this->assertSanitized('a { color: red; @supports { color: blue } }', "a {\n  color: red;\n}\n", ['prelude @supports@1']);
        $this->assertSanitized('a { color: red; @media tv { color: blue } @supports (background: url(x)) { color: blue } @media print; @media print { position: fixed } }', "a {\n  color: red;\n}\n", ['prelude @media tv@1', 'prelude @supports (background: url("x"))@1', 'syntax @media@1', 'property position@1']);

        $policy = new Policy()->withoutAtRules(AtRuleKind::Media, AtRuleKind::Supports);

        $this->assertSanitized('a { color: red; @media print { color: blue } @supports (display: grid) { color: blue } }', "a {\n  color: red;\n}\n", ['at_rule @media@1', 'at_rule @supports@1'], $policy);
        $this->assertSanitized('a { color: red; @import "x.css"; @keyframes k {} }', "a {\n  color: red;\n}\n", ['at_rule @import@1', 'at_rule @keyframes@1']);
    }

    public function testItDropsNestedRulesLeftEmpty(): void
    {
        $this->assertSanitized('a { b { position: fixed } @media print { position: fixed } c:visited { color: red } }', '', ['property position@1', 'property position@1', 'selector c:visited@1']);
        $this->assertSanitized('a:visited { color: red; b { color: red } }', '', ['selector a:visited@1']);
    }

    /**
     * @param list<string> $removals
     */
    private function assertSanitized(string $css, string $expected, array $removals, Policy $policy = new Policy()): void
    {
        $sanitized = new CssSanitizer($policy)->sanitize($css);

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
