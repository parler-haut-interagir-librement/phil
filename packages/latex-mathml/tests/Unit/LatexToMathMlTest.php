<?php

declare(strict_types=1);

namespace Phil\LatexMathMl\Tests\Unit;

use DOMDocument;
use Phil\LatexMathMl\Exception\InvalidLatex;
use Phil\LatexMathMl\Internal\Alphabets;
use Phil\LatexMathMl\Internal\Lexer;
use Phil\LatexMathMl\Internal\Parser;
use Phil\LatexMathMl\Internal\Symbols;
use Phil\LatexMathMl\Internal\Token;
use Phil\LatexMathMl\LatexToMathMl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LatexToMathMl::class)]
#[CoversClass(Lexer::class)]
#[CoversClass(Parser::class)]
#[CoversClass(Token::class)]
#[CoversClass(Alphabets::class)]
#[CoversClass(Symbols::class)]
#[CoversClass(InvalidLatex::class)]
final class LatexToMathMlTest extends TestCase
{
    public function testWritesAMathElementWithTheFormulaAnnotated(): void
    {
        self::assertSame(
            '<math xmlns="http://www.w3.org/1998/Math/MathML" display="block"><semantics><mrow><mi>x</mi><mo>&lt;</mo><mi>y</mi></mrow><annotation encoding="application/x-tex">x&lt;y</annotation></semantics></math>',
            new LatexToMathMl()->convert('x<y'),
        );
        self::assertSame(
            '<math xmlns="http://www.w3.org/1998/Math/MathML"><semantics><mrow><mi>x</mi></mrow><annotation encoding="application/x-tex">x</annotation></semantics></math>',
            new LatexToMathMl()->convert(' x ', false),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function formulas(): iterable
    {
        yield 'empty' => ['', '<mrow></mrow>'];
        yield 'number' => ['3.14', '<mn>3.14</mn>'];
        yield 'operators' => ['a-b*c/d', '<mi>a</mi><mo>−</mo><mi>b</mi><mo>∗</mo><mi>c</mi><mo>/</mo><mi>d</mi>'];
        yield 'letters beyond ASCII' => ['é+→', '<mi>é</mi><mo>+</mo><mo>→</mo>'];
        yield 'superscript' => ['x^2', '<msup><mi>x</mi><mn>2</mn></msup>'];
        yield 'one digit of a number' => ['x^123', '<msup><mi>x</mi><mn>1</mn></msup><mn>23</mn>'];
        yield 'subscript and superscript' => ['x_i^{n+1}', '<msubsup><mi>x</mi><mi>i</mi><mrow><mi>n</mi><mo>+</mo><mn>1</mn></mrow></msubsup>'];
        yield 'superscript then subscript' => ['x^a_b', '<msubsup><mi>x</mi><mi>b</mi><mi>a</mi></msubsup>'];
        yield 'subscript alone' => ['x_ 1', '<msub><mi>x</mi><mn>1</mn></msub>'];
        yield 'script without base' => ['^2', '<msup><mrow></mrow><mn>2</mn></msup>'];
        yield 'subscript without base' => ['_2', '<msub><mrow></mrow><mn>2</mn></msub>'];
        yield 'primes' => ["f''", '<msup><mi>f</mi><mo>′′</mo></msup>'];
        yield 'prime and power' => ["f'^2", '<msup><mi>f</mi><mrow><mo>′</mo><mn>2</mn></mrow></msup>'];
        yield 'prime alone' => ["'", '<mo>′</mo>'];
        yield 'group' => ['{ab}^2', '<msup><mrow><mi>a</mi><mi>b</mi></mrow><mn>2</mn></msup>'];
        yield 'empty group' => ['{}', '<mrow></mrow>'];
        yield 'greek' => ['\alpha\Omega\infty', '<mi>α</mi><mi mathvariant="normal">Ω</mi><mi>∞</mi>'];
        yield 'symbols' => ['\leq\to\{\}\|\&\%', '<mo>≤</mo><mo>→</mo><mo>{</mo><mo>}</mo><mo>‖</mo><mo>&amp;</mo><mo>%</mo>'];
        yield 'fence commands' => ['\langle x \rangle', '<mo>⟨</mo><mi>x</mi><mo>⟩</mo>'];
        yield 'sum' => ['\sum_{i=1}^n i', '<munderover><mo>∑</mo><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></munderover><mi>i</mi>'];
        yield 'sum over only' => ['\prod^n', '<mover><mo>∏</mo><mi>n</mi></mover>'];
        yield 'sum under only' => ['\bigcup_i', '<munder><mo>⋃</mo><mi>i</mi></munder>'];
        yield 'sum without limits' => ['\sum\nolimits_i', '<msub><mo>∑</mo><mi>i</mi></msub>'];
        yield 'integral' => ['\int_0^1', '<msubsup><mo>∫</mo><mn>0</mn><mn>1</mn></msubsup>'];
        yield 'integral with limits' => ['\int\limits_0', '<munder><mo>∫</mo><mn>0</mn></munder>'];
        yield 'function' => ['\sin x', '<mi>sin</mi><mo>&#x2061;</mo><mi>x</mi>'];
        yield 'function with power' => ['\sin^2 x', '<msup><mi>sin</mi><mn>2</mn></msup><mo>&#x2061;</mo><mi>x</mi>'];
        yield 'limit' => ['\lim_{x\to0}', '<munder><mo movablelimits="true">lim</mo><mrow><mi>x</mi><mo>→</mo><mn>0</mn></mrow></munder><mo>&#x2061;</mo>'];
        yield 'operator name' => ['\operatorname{ sgn }', '<mi>sgn</mi><mo>&#x2061;</mo>'];
        yield 'fraction' => ['\frac{a}{b}', '<mfrac><mi>a</mi><mi>b</mi></mfrac>'];
        yield 'fraction of digits' => ['\frac12', '<mfrac><mn>1</mn><mn>2</mn></mfrac>'];
        yield 'display fraction' => ['\dfrac ab', '<mstyle displaystyle="true"><mfrac><mi>a</mi><mi>b</mi></mfrac></mstyle>'];
        yield 'text fraction' => ['\tfrac ab', '<mstyle displaystyle="false"><mfrac><mi>a</mi><mi>b</mi></mfrac></mstyle>'];
        yield 'binomial' => ['\binom nk', '<mrow><mo>(</mo><mfrac linethickness="0"><mi>n</mi><mi>k</mi></mfrac><mo>)</mo></mrow>'];
        yield 'square root' => ['\sqrt{x}', '<msqrt><mi>x</mi></msqrt>'];
        yield 'cube root' => ['\sqrt[3]{x}', '<mroot><mi>x</mi><mn>3</mn></mroot>'];
        yield 'root of a sum' => ['\sqrt[n+1] x', '<mroot><mi>x</mi><mrow><mi>n</mi><mo>+</mo><mn>1</mn></mrow></mroot>'];
        yield 'accent' => ['\hat x', '<mover accent="true"><mi>x</mi><mo stretchy="false">^</mo></mover>'];
        yield 'wide accent' => ['\overline{ab}', '<mover accent="true"><mrow><mi>a</mi><mi>b</mi></mrow><mo stretchy="true">‾</mo></mover>'];
        yield 'brace over' => ['\overbrace{a}^n', '<mover><mover accent="true"><mi>a</mi><mo stretchy="true">⏞</mo></mover><mi>n</mi></mover>'];
        yield 'underline' => ['\underline x', '<munder accentunder="true"><mi>x</mi><mo stretchy="true">_</mo></munder>'];
        yield 'brace under' => ['\underbrace{a}_n', '<munder><munder accentunder="true"><mi>a</mi><mo stretchy="true">⏟</mo></munder><mi>n</mi></munder>'];
        yield 'over and under set' => ['\overset{!}{=}\underset{x}{\max}', '<mover><mo>=</mo><mo>!</mo></mover><munder><mrow><mo movablelimits="true">max</mo><mo>&#x2061;</mo></mrow><mi>x</mi></munder>'];
        yield 'spaces' => ['a\,b\quad c~d\ e\!f', '<mi>a</mi><mspace width="0.1667em"></mspace><mi>b</mi><mspace width="1em"></mspace><mi>c</mi><mspace width="0.25em"></mspace><mi>d</mi><mspace width="0.25em"></mspace><mi>e</mi><mspace width="-0.1667em"></mspace><mi>f</mi>'];
        yield 'text' => ['\text{if } x', '<mtext>if </mtext><mi>x</mi>'];
        yield 'text with escapes and groups' => ['\text{a \& {b}\ c~d<}', '<mtext>a &amp; b c d&lt;</mtext>'];
        yield 'text of one character' => ['\text a', '<mtext>a</mtext>'];
        yield 'bold and italic text' => ['\textbf{Ab1}\textit{h}', '<mtext>𝐀𝐛𝟏</mtext><mtext>ℎ</mtext>'];
        yield 'bold' => ['\mathbf{v1}', '<mrow><mi>𝐯</mi><mn>𝟏</mn></mrow>'];
        yield 'double struck' => ['\mathbb{RZ1}', '<mrow><mi>ℝ</mi><mi>ℤ</mi><mn>𝟙</mn></mrow>'];
        yield 'script' => ['\mathcal{LA}', '<mrow><mi>ℒ</mi><mi>𝒜</mi></mrow>'];
        yield 'fraktur without digits' => ['\mathfrak{g2}', '<mrow><mi>𝔤</mi><mn>2</mn></mrow>'];
        yield 'upright' => ['\mathrm{d}x', '<mi mathvariant="normal">d</mi><mi>x</mi>'];
        yield 'italic number' => ['\mathit{3}', '<mn>3</mn>'];
        yield 'monospace and sans serif' => ['\mathtt{a}\mathsf{a}', '<mi>𝚊</mi><mi>𝖺</mi>'];
        yield 'font of symbols' => ['\mathbf{\alpha+}', '<mrow><mi>α</mi><mo>+</mo></mrow>'];
        yield 'fence' => ['\left( x \right]', '<mrow><mo fence="true" stretchy="true">(</mo><mi>x</mi><mo fence="true" stretchy="true">]</mo></mrow>'];
        yield 'fence with middle' => ['\left\{ x \middle\| y \right.', '<mrow><mo fence="true" stretchy="true">{</mo><mi>x</mi><mo fence="true" stretchy="true">‖</mo><mi>y</mi></mrow>'];
        yield 'fence of commands' => ['\left\langle x\right\rangle', '<mrow><mo fence="true" stretchy="true">⟨</mo><mi>x</mi><mo fence="true" stretchy="true">⟩</mo></mrow>'];
        yield 'sized delimiters' => ['\big( \Bigr) \bigg| \Biggl.', '<mo minsize="1.2em" maxsize="1.2em">(</mo><mo minsize="1.623em" maxsize="1.623em">)</mo><mo minsize="2.047em" maxsize="2.047em">|</mo><mrow></mrow>'];
        yield 'matrix' => ['\begin{matrix}a&b\\\\c&d\end{matrix}', '<mtable><mtr><mtd><mi>a</mi></mtd><mtd><mi>b</mi></mtd></mtr><mtr><mtd><mi>c</mi></mtd><mtd><mi>d</mi></mtd></mtr></mtable>'];
        yield 'parenthesized matrix, last break' => ['\begin{pmatrix} 1 \\\\ \end{pmatrix}', '<mrow><mo fence="true" stretchy="true">(</mo><mtable><mtr><mtd><mn>1</mn></mtd></mtr></mtable><mo fence="true" stretchy="true">)</mo></mrow>'];
        yield 'empty matrix' => ['\begin{bmatrix}\end{bmatrix}', '<mrow><mo fence="true" stretchy="true">[</mo><mtable><mtr><mtd><mrow></mrow></mtd></mtr></mtable><mo fence="true" stretchy="true">]</mo></mrow>'];
        yield 'cases' => ['\begin{cases}1&x\end{cases}', '<mrow><mo fence="true" stretchy="true">{</mo><mtable columnalign="left"><mtr><mtd><mn>1</mn></mtd><mtd><mi>x</mi></mtd></mtr></mtable></mrow>'];
        yield 'aligned, with a space after the break' => ['\begin{aligned}a&=b\\\\[2pt]&=c\end{aligned}', '<mtable columnalign="right left right left"><mtr><mtd><mi>a</mi></mtd><mtd><mrow><mo>=</mo><mi>b</mi></mrow></mtd></mtr><mtr><mtd><mrow></mrow></mtd><mtd><mrow><mo>=</mo><mi>c</mi></mrow></mtd></mtr></mtable>'];
        yield 'array' => ['\begin{array}{l cr}a\end{array}', '<mtable columnalign="left center right"><mtr><mtd><mi>a</mi></mtd></mtr></mtable>'];
        yield 'array without columns' => ['\begin{array}{}a\end{array}', '<mtable><mtr><mtd><mi>a</mi></mtd></mtr></mtable>'];
        yield 'vertical bars matrix' => ['\begin{Vmatrix}a\end{Vmatrix}', '<mrow><mo fence="true" stretchy="true">‖</mo><mtable><mtr><mtd><mi>a</mi></mtd></mtr></mtable><mo fence="true" stretchy="true">‖</mo></mrow>'];
        yield 'negation' => ['\not= \not\in \not x', '<mo>='."\u{338}".'</mo><mo>∈'."\u{338}".'</mo><mrow><mi>x</mi><mo>'."\u{338}".'</mo></mrow>'];
        yield 'modulo' => ['a\bmod b \pmod n \mod m', '<mi>a</mi><mo>mod</mo><mi>b</mi><mrow><mspace width="1em"></mspace><mo>(</mo><mi>mod</mi><mspace width="0.3333em"></mspace><mi>n</mi><mo>)</mo></mrow><mrow><mspace width="1em"></mspace><mi>mod</mi><mspace width="0.3333em"></mspace><mi>m</mi></mrow>'];
        yield 'display style' => ['a\displaystyle b c', '<mi>a</mi><mstyle displaystyle="true"><mrow><mi>b</mi><mi>c</mi></mrow></mstyle>'];
        yield 'text style in a group' => ['{\textstyle b}c', '<mrow><mstyle displaystyle="false"><mi>b</mi></mstyle></mrow><mi>c</mi>'];
        yield 'comment' => ["a % gone\n+b", '<mi>a</mi><mo>+</mo><mi>b</mi>'];
        yield 'delimiters' => ['$$x$$', '<mi>x</mi>'];
        yield 'inline delimiters' => ['$x$', '<mi>x</mi>'];
        yield 'bracket delimiters' => ['\[ x \]', '<mi>x</mi>'];
        yield 'parenthesis delimiters' => ['\(x\)', '<mi>x</mi>'];
        yield 'a lone dollar is not a delimiter' => ['\$', '<mo>$</mo>'];
        yield 'an empty pair of dollars' => ['$$', '<mrow></mrow>'];
        yield 'aliases of fractions' => ['\cfrac ab\dbinom ab\tbinom ab', '<mfrac><mi>a</mi><mi>b</mi></mfrac><mrow><mo>(</mo><mfrac linethickness="0"><mi>a</mi><mi>b</mi></mfrac><mo>)</mo></mrow><mrow><mo>(</mo><mfrac linethickness="0"><mi>a</mi><mi>b</mi></mfrac><mo>)</mo></mrow>'];
        yield 'stackrel' => ['\stackrel{!}{=}', '<mover><mo>=</mo><mo>!</mo></mover>'];
        yield 'aliases of text' => ['\textrm a\mbox b\textnormal c', '<mtext>a</mtext><mtext>b</mtext><mtext>c</mtext>'];
        yield 'aliases of align' => ['\begin{align}a\end{align}\begin{align*}b\end{align*}', '<mtable columnalign="right left right left"><mtr><mtd><mi>a</mi></mtd></mtr></mtable><mtable columnalign="right left right left"><mtr><mtd><mi>b</mi></mtd></mtr></mtable>'];
        yield 'environment names with spaces' => ['\begin{ matrix }a\end{ matrix }', '<mtable><mtr><mtd><mi>a</mi></mtd></mtr></mtable>'];
        yield 'a matrix among other atoms' => ['x\begin{matrix}a\end{matrix}', '<mi>x</mi><mtable><mtr><mtd><mi>a</mi></mtd></mtr></mtable>'];
        yield 'quotes in text' => ['\text{"it\'s"}', '<mtext>&quot;it&apos;s&quot;</mtext>'];
        yield 'upright digits' => ['\mathrm{2x}', '<mrow><mn>2</mn><mi mathvariant="normal">x</mi></mrow>'];
        yield 'nested groups in text' => ['\text{a{b}c}d', '<mtext>abc</mtext><mi>d</mi>'];
    }

    #[DataProvider('formulas')]
    public function testConvertsEachConstruct(string $latex, string $mathml): void
    {
        $fragment = new DOMDocument();
        $fragment->loadXML('<r>'.$mathml.'</r>');

        $single = 1 === $fragment->documentElement?->childElementCount && 'mrow' === $fragment->documentElement->firstElementChild?->nodeName;
        $expected = $single ? $mathml : '<mrow>'.$mathml.'</mrow>';
        $converted = new LatexToMathMl()->convert($latex, false);

        self::assertSame($expected, substr($converted, \strlen('<math xmlns="http://www.w3.org/1998/Math/MathML"><semantics>'), -\strlen('</annotation></semantics></math>') - \strlen('<annotation encoding="application/x-tex">'.htmlspecialchars($this->undelimited($latex), \ENT_QUOTES | \ENT_XML1))));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalid(): iterable
    {
        yield 'unknown command' => ['x+\foo', 'at byte 2: "\foo" is not supported.'];
        yield 'unbalanced closing brace' => ['x}', 'at byte 1: this closing brace opens nothing.'];
        yield 'closing brace first' => ['}', 'at byte 0: this closing brace opens nothing.'];
        yield 'open group' => ['{x', 'at byte 0: this group is never closed.'];
        yield 'open root index' => ['\sqrt[3', 'at byte 5: this group is never closed.'];
        yield 'open text' => ['\text{ab', 'at byte 5: this group is never closed.'];
        yield 'two superscripts' => ['x^2^3', 'at byte 3: an atom has two superscripts'];
        yield 'two subscripts' => ['x_2_3', 'at byte 3: an atom has two superscripts'];
        yield 'two primes groups' => ["x'^2'", 'at byte 4: an atom has two superscripts'];
        yield 'script without argument' => ['x^', 'at byte 2: "^" needs an argument.'];
        yield 'command without argument' => ['\frac{a}', 'at byte 8: "\frac" needs an argument.'];
        yield 'argument cut by a brace' => ['{\hat}', 'at byte 5: "\hat" needs an argument.'];
        yield 'argument cut by a column' => ['\begin{matrix}\hat&\end{matrix}', 'at byte 18: "\hat" needs an argument.'];
        yield 'column outside an environment' => ['a&b', 'at byte 1: a column separator "&" is only allowed in an environment.'];
        yield 'line break outside an environment' => ['a\\\\b', 'at byte 1: a line break "\\\\" is only allowed in an environment.'];
        yield 'right without left' => ['\right)', '"\right" has no "\left"'];
        yield 'end without begin' => ['\end{matrix}', '"\end" has no "\begin"'];
        yield 'middle outside a fence' => ['\middle|', '"\middle" is only allowed between "\left" and "\right"'];
        yield 'left without right' => ['\left( x', 'at byte 0: "\left" has no "\right".'];
        yield 'left without delimiter' => ['\left x', 'at byte 6: "\left" needs a delimiter.'];
        yield 'big without delimiter' => ['\big', 'at byte 4: "\big" needs a delimiter.'];
        yield 'unknown environment' => ['\begin{tabular}x\end{tabular}', 'at byte 0: the environment "tabular" is not supported.'];
        yield 'mismatched environment' => ['\begin{matrix}x\end{pmatrix}', '"\begin{matrix}" is closed by "\end{pmatrix}"'];
        yield 'environment never ended' => ['\begin{matrix}x', 'at byte 0: this environment is never ended.'];
        yield 'not before nothing' => ['\not', 'at byte 0: "\not" needs an operator.'];
        yield 'command in a text' => ['\text{\alpha}', 'at byte 6: "\alpha" is not allowed in a text.'];
        yield 'text without a group' => ['\text\alpha', 'at byte 5: "\text" needs a group.'];
        yield 'text at the end' => ['\text', 'at byte 5: "\text" needs a group.'];
        yield 'lone backslash' => ['x\\', 'at byte 1: a backslash must be followed by a command.'];
        yield 'hash' => ['#1', 'at byte 0: "#" must be written "\#".'];
        yield 'dollar inside' => ['a$b', 'at byte 1: "$" must be written "\$".'];
        yield 'not UTF-8' => ["x\xff", 'at byte 0: the formula is not valid UTF-8.'];
        yield 'nothing after a fraction' => ['\frac', 'at byte 5: "\frac" needs an argument.'];
        yield 'a script where a value is expected' => ['x^^2', 'at byte 2: a value is expected here.'];
        yield 'a lone dollar' => ['$', 'at byte 0: "$" must be written "\$".'];
        yield 'a long command in a text' => ['\text{\times}', 'at byte 6: "\times" is not allowed in a text.'];
        yield 'a space command in a text' => ['\text{a\,b}', 'at byte 7: "\," is not allowed in a text.'];
    }

    #[DataProvider('invalid')]
    public function testRefusesWhatItCannotConvert(string $latex, string $message): void
    {
        $this->expectException(InvalidLatex::class);
        $this->expectExceptionMessageIsOrContains($message);

        new LatexToMathMl()->convert($latex);
    }

    public function testEscapesTheAnnotation(): void
    {
        self::assertStringContainsString('<annotation encoding="application/x-tex">\\text{&quot;it&apos;s&quot;}</annotation>', new LatexToMathMl()->convert('\text{"it\'s"}'));
    }

    public function testGivesTheOffsetOfTheProblem(): void
    {
        try {
            new LatexToMathMl()->convert('a + \nope');
            self::fail('The command is refused.');
        } catch (InvalidLatex $invalidLatex) {
            self::assertSame(4, $invalidLatex->offset);
        }
    }

    public function testBoundsTheLengthAndTheDepth(): void
    {
        $converter = new LatexToMathMl(10, 3);

        self::assertStringContainsString('<mn>1234567890</mn>', $converter->convert('1234567890'));
        self::assertStringContainsString('<mrow><mrow><mrow><mi>x</mi></mrow></mrow></mrow>', $converter->convert('{{{x}}}'));

        foreach (['12345678901' => 'longer than 10 bytes', '{{{{x}}}}' => 'nests deeper than 3 levels', '\hat\hat\hat\hat x' => 'nests deeper than 3 levels', '\left(\left(\left(\left(' => 'nests deeper than 3 levels', '\begin{matrix}\begin{matrix}\begin{matrix}\begin{matrix}' => 'nests deeper'] as $latex => $message) {
            try {
                new LatexToMathMl('12345678901' === (string) $latex ? 10 : 1000, 3)->convert((string) $latex);
                self::fail($latex.' is refused.');
            } catch (InvalidLatex $exception) {
                self::assertStringContainsString($message, $exception->getMessage());
            }
        }
    }

    public function testCountsTheDepthOfEachGroupApart(): void
    {
        self::assertSame(8, substr_count(new LatexToMathMl(1000, 1)->convert(str_repeat('{x}', 8)), '<mi>x</mi>'));
        self::assertSame(4, substr_count(new LatexToMathMl(1000, 1)->convert('\hat a\hat b\hat c\hat d'), '<mover'));
    }

    public function testBoundsTheCellsOfAnEnvironment(): void
    {
        $this->expectExceptionMessageIsOrContains('the environment holds more than 2500 cells');

        new LatexToMathMl(100_000)->convert('\begin{matrix}'.str_repeat('&', 2500).'\end{matrix}');
    }

    public function testAcceptsTheLargestEnvironment(): void
    {
        self::assertSame(2500, substr_count(new LatexToMathMl(100_000)->convert('\begin{matrix}'.str_repeat('&', 2499).'\end{matrix}'), '<mtd>'));
    }

    public function testDefaultLimits(): void
    {
        self::assertStringContainsString('<mn>', new LatexToMathMl()->convert(str_repeat('1', 20_000)));
        self::assertStringContainsString('<mi>x</mi>', new LatexToMathMl()->convert(str_repeat('{', 64).'x'.str_repeat('}', 64)));

        $this->expectExceptionMessageIsOrContains('longer than 20000 bytes');
        new LatexToMathMl()->convert(str_repeat('1', 20_001));
    }

    public function testDefaultDepth(): void
    {
        $this->expectExceptionMessageIsOrContains('deeper than 64 levels');

        new LatexToMathMl()->convert(str_repeat('{', 65).'x'.str_repeat('}', 65));
    }

    public function testKeepsCharactersOutsideTheAlphabets(): void
    {
        self::assertSame('é-', Alphabets::apply('é-', 'bold'));
        self::assertSame('Ab', Alphabets::apply('Ab', 'normal'));
        self::assertSame('𝐳𝐙𝟗', Alphabets::apply('zZ9', 'bold'));
        self::assertSame('9', Alphabets::apply('9', 'script'));
    }

    private function undelimited(string $latex): string
    {
        foreach ([['$$', '$$'], ['\[', '\]'], ['\(', '\)'], ['$', '$']] as [$open, $close]) {
            if (\strlen($latex) > 1 && str_starts_with($latex, $open) && str_ends_with($latex, $close) && \strlen($latex) >= \strlen($open.$close)) {
                return trim(substr($latex, \strlen($open), -\strlen($close)));
            }
        }

        return trim($latex);
    }
}
