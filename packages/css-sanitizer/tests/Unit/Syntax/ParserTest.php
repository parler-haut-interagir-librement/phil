<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Tests\Unit\Syntax;

use Phil\CssSanitizer\Exception\NestingTooDeep;
use Phil\CssSanitizer\Syntax\AtRule;
use Phil\CssSanitizer\Syntax\ComponentValue;
use Phil\CssSanitizer\Syntax\Declaration;
use Phil\CssSanitizer\Syntax\FunctionValue;
use Phil\CssSanitizer\Syntax\Malformed;
use Phil\CssSanitizer\Syntax\Parser;
use Phil\CssSanitizer\Syntax\QualifiedRule;
use Phil\CssSanitizer\Syntax\SimpleBlock;
use Phil\CssSanitizer\Syntax\Stylesheet;
use Phil\CssSanitizer\Syntax\Token;
use Phil\CssSanitizer\Syntax\Tokenizer;
use Phil\CssSanitizer\Syntax\TokenType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Parser::class)]
#[CoversClass(AtRule::class)]
#[CoversClass(Declaration::class)]
#[CoversClass(FunctionValue::class)]
#[CoversClass(Malformed::class)]
#[CoversClass(QualifiedRule::class)]
#[CoversClass(SimpleBlock::class)]
#[CoversClass(Stylesheet::class)]
#[CoversClass(Token::class)]
#[CoversClass(NestingTooDeep::class)]
#[UsesClass(Tokenizer::class)]
final class ParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<mixed>}>
     */
    public static function stylesheets(): iterable
    {
        yield 'empty' => ['', []];
        yield 'style rule' => ['a { color: red }', [['rule', 'a ', '{ color: red }']]];
        yield 'two rules and white space' => [" a{}\n b{} ", [['rule', 'a', '{}'], ['rule', 'b', '{}']]];
        yield 'at-rule with block' => ['@media print { a {} }', [['@media', ' print ', '{ a {} }']]];
        yield 'at-rule with semicolon' => ['@import "a.css"; a{}', [['@import', ' "a.css"', null], ['rule', 'a', '{}']]];
        yield 'at-rule cut by the end' => ['@charset "x"', [['@charset', ' "x"', null]]];
        yield 'qualified rule cut by the end' => ['a b', [['malformed', 'a b']]];
        yield 'CDO and CDC skipped at the top' => ['<!-- a{} -->', [['rule', 'a', '{}']]];
        yield 'stray closing brace kept in the prelude' => ['} a {}', [['rule', '} a ', '{}']]];
        yield 'block in the prelude' => ['a[x] (b) {}', [['rule', 'a[x] (b) ', '{}']]];
    }

    /**
     * @param list<mixed> $expected
     */
    #[DataProvider('stylesheets')]
    public function testItParsesStylesheets(string $css, array $expected): void
    {
        self::assertSame($expected, array_map($this->rule(...), new Parser()->parseStylesheet($css)->rules));
    }

    public function testItKeepsCdoAndCdcInNestedRuleLists(): void
    {
        $parser = new Parser();
        $rules = $parser->rules($parser->parseComponentValues('<!-- a{}'));

        self::assertSame([['rule', '<!-- a', '{}']], array_map($this->rule(...), $rules));
        self::assertSame([['rule', 'a', '{}']], array_map($this->rule(...), $parser->rules($parser->parseComponentValues('-->a{}'), true)));
    }

    /**
     * @return iterable<string, array{string, list<mixed>}>
     */
    public static function declarationLists(): iterable
    {
        yield 'empty' => ['', []];
        yield 'one' => ['color:red', [['color', 'red', false]]];
        yield 'white space around' => ['  color  :  red  ;  ', [['color', 'red', false]]];
        yield 'several' => ['a: 1; b: 2 3;;c:4', [['a', '1', false], ['b', '2 3', false], ['c', '4', false]]];
        yield 'important' => ['a: 1 !important; b: 2 ! IMPORTANT ; c: 3!important', [['a', '1', true], ['b', '2', true], ['c', '3', true]]];
        yield 'important alone' => ['a: !important', [['a', '', true]]];
        yield 'not important' => ['a: important; b: x important; c: !x; d: 1 !important x', [['a', 'important', false], ['b', 'x important', false], ['c', '!x', false], ['d', '1 !important x', false]]];
        yield 'empty value' => ['a:;b: ;', [['a', '', false], ['b', '', false]]];
        yield 'missing colon' => ['a b; c: d', [['malformed', 'a b'], ['c', 'd', false]]];
        yield 'name alone' => ['a', [['malformed', 'a']]];
        yield 'not an ident' => ['1px: x; {a: b} ; c: d', [['malformed', '1px: x'], ['malformed', '{a: b} '], ['c', 'd', false]]];
        yield 'at-rule among declarations' => ['a: 1; @top-left { b: 2 } c: 3', [['a', '1', false], ['@top-left', ' ', '{ b: 2 }'], ['c', '3', false]]];
        yield 'semicolon inside a block' => ['a: f(x;y) [;] ; b: 1', [['a', 'f(x;y) [;]', false], ['b', '1', false]]];
    }

    /**
     * @param list<mixed> $expected
     */
    #[DataProvider('declarationLists')]
    public function testItParsesDeclarationLists(string $css, array $expected): void
    {
        self::assertSame($expected, array_map($this->rule(...), new Parser()->parseDeclarationList($css)));
    }

    /**
     * @return iterable<string, array{string, list<mixed>}>
     */
    public static function blockContents(): iterable
    {
        yield 'empty' => ['', []];
        yield 'declarations' => ['a: 1; ; b: 2', [['a', '1', false], ['b', '2', false]]];
        yield 'nested rule' => ['& b { c: 1 } d: 2', [['rule', '& b ', '{ c: 1 }'], ['d', '2', false]]];
        yield 'rule read after a failed declaration' => ['a:hover { c: 1 } d: 2;', [['rule', 'a:hover ', '{ c: 1 }'], ['d', '2', false]]];
        yield 'name without colon' => ['em { c: 1 }', [['rule', 'em ', '{ c: 1 }']]];
        yield 'declaration whose value is a block' => ['a: { b }', [['a', '{ b }', false]]];
        yield 'custom property holding a block' => ['--a: { b } c; d: 1', [['--a', '{ b } c', false], ['d', '1', false]]];
        yield 'rule cut by a semicolon' => ['& b; c: 1', [['malformed', '& b'], ['c', '1', false]]];
        yield 'rule cut by the end' => ['> b', [['malformed', '> b']]];
        yield 'at-rule' => ['@media print { a: 1 } b: 2', [['@media', ' print ', '{ a: 1 }'], ['b', '2', false]]];
        yield 'white space before the colon' => ['a : 1', [['a', '1', false]]];
        yield 'name and white space at the end' => ['em ', [['malformed', 'em ']]];
        yield 'several values' => ['a: b c; d: 1', [['a', 'b c', false], ['d', '1', false]]];
        yield 'rule whose block ends the contents' => ['em:hover { c: 1 }', [['rule', 'em:hover ', '{ c: 1 }']]];
        yield 'colon after what is not a name' => ['&: x; c: 1', [['malformed', '&: x'], ['c', '1', false]]];
    }

    /**
     * @param list<mixed> $expected
     */
    #[DataProvider('blockContents')]
    public function testItReadsBlockContents(string $css, array $expected): void
    {
        $parser = new Parser();

        self::assertSame($expected, array_map($this->rule(...), $parser->blockContents($parser->parseComponentValues($css))));
    }

    public function testItKeepsTheLinesOfNestedRules(): void
    {
        $parser = new Parser();
        $items = $parser->blockContents($parser->parseComponentValues("a: 1;\n& b { }\n& c;"));

        self::assertSame([1, 2, 3], array_map(static fn (Declaration|AtRule|QualifiedRule|Malformed $item): int => $item->line, $items));
    }

    public function testItReadsDeclarationsFromComponentValues(): void
    {
        $parser = new Parser();

        self::assertSame([['a', 'b', false]], array_map(self::rule(...), $parser->declarations($parser->parseComponentValues('a: b'))));
    }

    public function testItReadsComponentValues(): void
    {
        $values = new Parser()->parseComponentValues('a f(b, [c]) {d} (e');

        self::assertCount(7, $values);
        self::assertInstanceOf(FunctionValue::class, $values[2]);
        self::assertSame('f', $values[2]->name);
        self::assertSame('b, [c]', self::text($values[2]->arguments));
        self::assertInstanceOf(SimpleBlock::class, $values[4]);
        self::assertSame(TokenType::OpenCurly, $values[4]->opening);
        self::assertSame('d', self::text($values[4]->value));
        self::assertInstanceOf(SimpleBlock::class, $values[6]);
        self::assertSame(TokenType::OpenParenthesis, $values[6]->opening);
        self::assertSame('e', self::text($values[6]->value));
    }

    public function testItClosesBlocksOnlyWithTheirOwnToken(): void
    {
        $values = new Parser()->parseComponentValues('[a) b] c');

        self::assertCount(3, $values);
        self::assertInstanceOf(SimpleBlock::class, $values[0]);
        self::assertSame(TokenType::OpenSquare, $values[0]->opening);
        self::assertSame('a) b', self::text($values[0]->value));
    }

    public function testItKeepsLines(): void
    {
        $parser = new Parser();
        $rules = $parser->parseStylesheet("\n a\n{\n b: c;\n}\n@media x {}\nz");

        self::assertInstanceOf(QualifiedRule::class, $rules->rules[0]);
        self::assertSame(2, $rules->rules[0]->line);
        self::assertSame(3, $rules->rules[0]->block->line());
        self::assertInstanceOf(AtRule::class, $rules->rules[1]);
        self::assertSame(6, $rules->rules[1]->line);
        self::assertInstanceOf(Malformed::class, $rules->rules[2]);
        self::assertSame(7, $rules->rules[2]->line);

        $items = $parser->parseDeclarationList("\na: b;\n\nc d;\n@x;\nf(");

        self::assertInstanceOf(Declaration::class, $items[0]);
        self::assertSame(2, $items[0]->line);
        self::assertInstanceOf(Malformed::class, $items[1]);
        self::assertSame(4, $items[1]->line);
        self::assertInstanceOf(AtRule::class, $items[2]);
        self::assertSame(5, $items[2]->line);
        self::assertInstanceOf(Malformed::class, $items[3]);
        self::assertSame(6, $items[3]->line);
        self::assertSame(6, $items[3]->content[0]->line());
    }

    public function testItLimitsNesting(): void
    {
        $parser = new Parser(3);

        self::assertCount(1, $parser->parseComponentValues('((( a )))'));
        self::assertCount(1, $parser->parseComponentValues('f(g([a]))'));

        $this->expectException(NestingTooDeep::class);
        $this->expectExceptionMessageIsOrContains('The CSS nests blocks and functions deeper than 3 levels, on line 2.');

        $parser->parseComponentValues("(((\n{a})))");
    }

    public function testItNestsSixtyFourLevelsByDefault(): void
    {
        self::assertCount(1, new Parser()->parseComponentValues(str_repeat('(', 64).str_repeat(')', 64)));

        $this->expectException(NestingTooDeep::class);

        new Parser()->parseComponentValues(str_repeat('(', 65));
    }

    public function testItTrimsAndSplitsAtCommas(): void
    {
        $parser = new Parser();

        self::assertSame('a  b', self::text(Parser::trim($parser->parseComponentValues(" \t a /**/ b \n "))));
        self::assertSame([], Parser::trim($parser->parseComponentValues('   ')));
        self::assertSame(['a', 'b c', '', 'f(d, e)'], array_map(self::text(...), Parser::splitAtCommas($parser->parseComponentValues(' a , b c,, f(d, e) '))));
        self::assertSame([''], array_map(self::text(...), Parser::splitAtCommas([])));
    }

    public function testDeclarationsTellCustomProperties(): void
    {
        $custom = new Declaration('--Main-Color', []);
        $plain = new Declaration('COLOR', []);

        self::assertTrue($custom->isCustomProperty());
        self::assertSame('--Main-Color', $custom->property());
        self::assertFalse($plain->isCustomProperty());
        self::assertSame('color', $plain->property());
        self::assertFalse(new Declaration('-webkit-x', [])->isCustomProperty());
    }

    public function testNamesAreComparedInLowerCase(): void
    {
        self::assertSame('media', new AtRule('MEDIA', [], null)->lowerName());
        self::assertSame('url', new FunctionValue('URL', [])->lowerName());
        self::assertTrue(new Token(TokenType::Ident, 'Important')->isIdent('important'));
        self::assertFalse(new Token(TokenType::String, 'important')->isIdent('important'));
        self::assertTrue(new Token(TokenType::Delim, '!')->is(TokenType::Delim));
        self::assertFalse(new Token(TokenType::Delim, '!')->is(TokenType::Delim, '?'));
        self::assertFalse(new Token(TokenType::Ident, '!')->is(TokenType::Delim, '!'));
        self::assertSame(1, new Token(TokenType::Ident)->line());
        self::assertSame(1, new SimpleBlock(TokenType::OpenCurly, [])->line());
        self::assertSame(1, new FunctionValue('f', [])->line());
        self::assertSame(1, new QualifiedRule([], new SimpleBlock(TokenType::OpenCurly, []))->line);
        self::assertSame(1, new AtRule('a', [], null)->line);
        self::assertSame(1, new Declaration('a', [])->line);
        self::assertSame(1, new Malformed([])->line);
        self::assertFalse(new Declaration('a', [])->important);
        self::assertFalse(new Token(TokenType::Hash)->isId);
        self::assertFalse(new Token(TokenType::Number)->isInteger);
        self::assertSame('', new Token(TokenType::Number)->unit);
        self::assertSame('', new Token(TokenType::Number)->value);
    }

    /**
     * @return list<mixed>
     */
    private function rule(QualifiedRule|AtRule|Malformed|Declaration $rule): array
    {
        return match (true) {
            $rule instanceof QualifiedRule => ['rule', self::text($rule->prelude), self::text([$rule->block])],
            $rule instanceof AtRule => ['@'.$rule->name, self::text($rule->prelude), $rule->block instanceof SimpleBlock ? self::text([$rule->block]) : null],
            $rule instanceof Malformed => ['malformed', self::text($rule->content)],
            default => [$rule->name, self::text($rule->value), $rule->important],
        };
    }

    /**
     * A plain rendering of component values for the assertions.
     *
     * @param list<ComponentValue> $values
     */
    private static function text(array $values): string
    {
        $text = '';

        foreach ($values as $value) {
            $text .= match (true) {
                $value instanceof FunctionValue => $value->name.'('.self::text($value->arguments).')',
                $value instanceof SimpleBlock => $value->opening->value.self::text($value->value).match ($value->opening) {
                    TokenType::OpenCurly => '}',
                    TokenType::OpenSquare => ']',
                    default => ')',
                },
                TokenType::String === $value->type => '"'.$value->value.'"',
                TokenType::Dimension === $value->type => $value->value.$value->unit,
                default => $value->value,
            };
        }

        return $text;
    }
}
