<?php

declare(strict_types=1);

namespace Phil\LatexMathMl\Tests\Unit;

use Phil\LatexMathMl\Exception\InvalidLatex;
use Phil\LatexMathMl\Internal\Chemistry;
use Phil\LatexMathMl\Internal\Colors;
use Phil\LatexMathMl\Internal\Lexer;
use Phil\LatexMathMl\Internal\Link;
use Phil\LatexMathMl\Internal\Macros;
use Phil\LatexMathMl\Internal\Parser;
use Phil\LatexMathMl\Internal\Token;
use Phil\LatexMathMl\LatexToMathMl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Macros, colours, links, boxes, equation numbers, rules, sizes and chemical notation.
 */
#[CoversClass(LatexToMathMl::class)]
#[CoversClass(Macros::class)]
#[CoversClass(Parser::class)]
#[CoversClass(Chemistry::class)]
#[CoversClass(Colors::class)]
#[CoversClass(Link::class)]
#[CoversClass(Lexer::class)]
#[CoversClass(Token::class)]
#[CoversClass(InvalidLatex::class)]
final class ExtensionsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function formulas(): iterable
    {
        yield 'macro without arguments' => ['\\newcommand{\\R}{\\mathbb{R}}x\\in\\R', '<mrow><mi>x</mi><mo>∈</mo><mi>ℝ</mi></mrow>'];
        yield 'macro without braces around its name' => ['\\newcommand\\R{\\mathbb R}\\R', '<mrow><mi>ℝ</mi></mrow>'];
        yield 'starred macro' => ['\\newcommand*{\\e}{\\mathrm{e}}\\e^x', '<mrow><msup><mi mathvariant="normal">e</mi><mi>x</mi></msup></mrow>'];
        yield 'macro with arguments' => ['\\newcommand{\\pair}[2]{(#1,#2)}\\pair{a}{b}', '<mrow><mo>(</mo><mi>a</mi><mo>,</mo><mi>b</mi><mo>)</mo></mrow>'];
        yield 'argument repeated' => ['\\newcommand{\\sq}[1]{#1#1}\\sq x', '<mrow><mi>x</mi><mi>x</mi></mrow>'];
        yield 'argument of one digit' => ['\\newcommand{\\f}[2]{#1^#2}\\f{x}{12}', '<mrow><msup><mi>x</mi><mn>1</mn></msup><mn>2</mn></mrow>'];
        yield 'one digit of a number as an argument' => ['\\newcommand{\\f}[1]{[#1]}\\f123', '<mrow><mo>[</mo><mn>1</mn><mo>]</mo><mn>23</mn></mrow>'];
        yield 'number after a parameter' => ['\\newcommand{\\f}[1]{#12}\\f a', '<mrow><mi>a</mi><mn>2</mn></mrow>'];
        yield 'macro using a macro' => ['\\newcommand{\\a}{x}\\newcommand{\\b}[1]{\\a+#1}\\b{\\a}', '<mrow><mi>x</mi><mo>+</mo><mi>x</mi></mrow>'];
        yield 'redefined macro' => ['\\newcommand{\\a}{x}\\renewcommand{\\a}{y}\\a', '<mrow><mi>y</mi></mrow>'];
        yield 'renewed command' => ['\\renewcommand{\\vec}[1]{\\mathbf{#1}}\\vec v', '<mrow><mi>𝐯</mi></mrow>'];
        yield 'def' => ['\\def\\f#1#2{#2#1}\\f ab', '<mrow><mi>b</mi><mi>a</mi></mrow>'];
        yield 'def without parameters' => ['\\def \\x {y}\\x', '<mrow><mi>y</mi></mrow>'];
        yield 'macro in text' => ['\\newcommand{\\w}{ok}\\text{\\w}', '<mrow><mtext>ok</mtext></mrow>'];
        yield 'zero arguments declared' => ['\\newcommand{\\z}[0]{z}\\z', '<mrow><mi>z</mi></mrow>'];
        yield 'colour of the rest' => ['a{\\color{red}b c}d', '<mrow><mi>a</mi><mrow><mstyle mathcolor="#ff0000"><mrow><mi>b</mi><mi>c</mi></mrow></mstyle></mrow><mi>d</mi></mrow>'];
        yield 'short hexadecimal colour' => ['\\color{#0F8}x', '<mrow><mstyle mathcolor="#00ff88"><mi>x</mi></mstyle></mrow>'];
        yield 'text colour' => ['\\textcolor{blue}{x}y', '<mrow><mstyle mathcolor="#0000ff"><mi>x</mi></mstyle><mi>y</mi></mrow>'];
        yield 'HTML colour' => ['\\textcolor[HTML]{FF8800}x', '<mrow><mstyle mathcolor="#ff8800"><mi>x</mi></mstyle></mrow>'];
        yield 'rgb colour' => ['\\textcolor[rgb]{1, 0.5,.25}x', '<mrow><mstyle mathcolor="#ff8040"><mi>x</mi></mstyle></mrow>'];
        yield 'rgb colour of ones and zeros' => ['\\textcolor[rgb]{0,1.0,0}x', '<mrow><mstyle mathcolor="#00ff00"><mi>x</mi></mstyle></mrow>'];
        yield 'link' => ['\\href{https://Example.com/a?b=1\\%20}{the site}', '<mrow><mtext><a xmlns="http://www.w3.org/1999/xhtml" href="https://example.com/a?b=1%20">the site</a></mtext></mrow>'];
        yield 'mail link' => ['\\href{mailto:me@EXAMPLE.org}{me}', '<mrow><mtext><a xmlns="http://www.w3.org/1999/xhtml" href="mailto:me@example.org">me</a></mtext></mrow>'];
        yield 'address' => ['\\url{ https://example.com/x_y }', '<mrow><mtext><a xmlns="http://www.w3.org/1999/xhtml" href="https://example.com/x_y">https://example.com/x_y</a></mtext></mrow>'];
        yield 'box' => ['\\boxed{x+1}', '<mrow><mrow style="border:0.06em solid;padding:0.2em"><mrow><mi>x</mi><mo>+</mo><mn>1</mn></mrow></mrow></mrow>'];
        yield 'tag' => ['E=mc^2\\tag{1}', '<mrow><mrow><mi>E</mi><mo>=</mo><mi>m</mi><msup><mi>c</mi><mn>2</mn></msup><mrow></mrow></mrow><mspace width="2em"></mspace><mtext>(1)</mtext></mrow>'];
        yield 'starred tag' => ['x\\tag*{A}', '<mrow><mrow><mi>x</mi><mrow></mrow></mrow><mspace width="2em"></mspace><mtext>A</mtext></mrow>'];
        yield 'no number' => ['x\\notag\\nonumber', '<mrow><mi>x</mi><mrow></mrow><mrow></mrow></mrow>'];
        yield 'tag in an aligned row' => ['\\begin{aligned}a&=b\\tag{1}\\\\c&=d\\\\e\\tag 3\\end{aligned}', '<mrow><mtable columnalign="right left right left"><mtr><mtd><mi>a</mi></mtd><mtd><mrow><mo>=</mo><mi>b</mi><mrow></mrow></mrow></mtd><mtd><mtext>(1)</mtext></mtd></mtr><mtr><mtd><mi>c</mi></mtd><mtd><mrow><mo>=</mo><mi>d</mi></mrow></mtd><mtd></mtd></mtr><mtr><mtd><mrow><mi>e</mi><mrow></mrow></mrow></mtd><mtd></mtd><mtd><mtext>(3)</mtext></mtd></mtr></mtable></mrow>'];
        yield 'tag on the last empty row' => ['\\begin{matrix}a\\\\\\tag{2}\\end{matrix}', '<mrow><mtable><mtr><mtd><mi>a</mi></mtd><mtd></mtd></mtr><mtr><mtd><mrow></mrow></mtd><mtd><mtext>(2)</mtext></mtd></mtr></mtable></mrow>'];
        yield 'tag outside and inside' => ['\\begin{matrix}a\\tag{1}\\end{matrix}\\tag{2}', '<mrow><mrow><mtable><mtr><mtd><mrow><mi>a</mi><mrow></mrow></mrow></mtd><mtd><mtext>(1)</mtext></mtd></mtr></mtable><mrow></mrow></mrow><mspace width="2em"></mspace><mtext>(2)</mtext></mrow>'];
        yield 'rules of an array' => ['\\begin{array}{|c|c|}\\hline a&b\\\\\\hline c&d\\\\\\hline\\end{array}', '<mrow><mtable columnalign="center center"><mtr><mtd style="border-top:0.06em solid;border-left:0.06em solid;border-right:0.06em solid"><mi>a</mi></mtd><mtd style="border-top:0.06em solid;border-right:0.06em solid"><mi>b</mi></mtd></mtr><mtr><mtd style="border-top:0.06em solid;border-bottom:0.06em solid;border-left:0.06em solid;border-right:0.06em solid"><mi>c</mi></mtd><mtd style="border-top:0.06em solid;border-bottom:0.06em solid;border-right:0.06em solid"><mi>d</mi></mtd></mtr></mtable></mrow>'];
        yield 'rule between columns only' => ['\\begin{array}{c|c}a&b\\end{array}', '<mrow><mtable columnalign="center center"><mtr><mtd style="border-right:0.06em solid"><mi>a</mi></mtd><mtd><mi>b</mi></mtd></mtr></mtable></mrow>'];
        yield 'rule before the first column' => ['\\begin{array}{|l}a\\end{array}', '<mrow><mtable columnalign="left"><mtr><mtd style="border-left:0.06em solid"><mi>a</mi></mtd></mtr></mtable></mrow>'];
        yield 'hline in a matrix' => ['\\begin{matrix}\\hline\\hline a\\end{matrix}', '<mrow><mtable><mtr><mtd style="border-top:0.06em solid"><mi>a</mi></mtd></mtr></mtable></mrow>'];
        yield 'hline alone' => ['\\begin{matrix}\\hline\\end{matrix}', '<mrow><mtable><mtr><mtd style="border-top:0.06em solid"><mrow></mrow></mtd></mtr></mtable></mrow>'];
        yield 'hline after a spaced break' => ['\\begin{matrix}a\\\\[2pt]\\hline b\\end{matrix}', '<mrow><mtable><mtr><mtd><mi>a</mi></mtd></mtr><mtr><mtd style="border-top:0.06em solid"><mi>b</mi></mtd></mtr></mtable></mrow>'];
        yield 'sizes' => ['\\tiny a\\scriptsize b', '<mrow><mstyle mathsize="0.5em"><mrow><mi>a</mi><mstyle mathsize="0.7em"><mi>b</mi></mstyle></mrow></mstyle></mrow>'];
        yield 'every size' => ['{\\footnotesize a}{\\small b}{\\normalsize c}{\\large d}{\\Large e}{\\LARGE f}{\\huge g}{\\Huge h}', '<mrow><mrow><mstyle mathsize="0.8em"><mi>a</mi></mstyle></mrow><mrow><mstyle mathsize="0.9em"><mi>b</mi></mstyle></mrow><mrow><mstyle mathsize="1em"><mi>c</mi></mstyle></mrow><mrow><mstyle mathsize="1.2em"><mi>d</mi></mstyle></mrow><mrow><mstyle mathsize="1.44em"><mi>e</mi></mstyle></mrow><mrow><mstyle mathsize="1.728em"><mi>f</mi></mstyle></mrow><mrow><mstyle mathsize="2.074em"><mi>g</mi></mstyle></mrow><mrow><mstyle mathsize="2.488em"><mi>h</mi></mstyle></mrow></mrow>'];
        yield 'script styles' => ['{\\scriptstyle a}{\\scriptscriptstyle b}', '<mrow><mrow><mstyle displaystyle="false" scriptlevel="1"><mi>a</mi></mstyle></mrow><mrow><mstyle displaystyle="false" scriptlevel="2"><mi>b</mi></mstyle></mrow></mrow>'];
        yield 'a condition over the arrow only' => ['\\ce{A ->[heat] B}', '<mrow><mi mathvariant="normal">A</mi><mover><mo stretchy="true">→</mo><mtext>heat</mtext></mover><mi mathvariant="normal">B</mi></mrow>'];
        yield 'a minus after a charge is a charge' => ['\\ce{C-}', '<mrow><msup><mi mathvariant="normal">C</mi><mo>−</mo></msup></mrow>'];
        yield 'a minus after a subscript is a bond' => ['\\ce{H2-C}', '<mrow><msub><mi mathvariant="normal">H</mi><mn>2</mn></msub><mo>−</mo><mi mathvariant="normal">C</mi></mrow>'];
        yield 'a charge with a letter before its sign' => ['\\ce{A^{x2+}}', '<mrow><msup><mi mathvariant="normal">A</mi><mtext>x2+</mtext></msup></mrow>'];
        yield 'a charge with a letter after its sign' => ['\\ce{A^{2+x}}', '<mrow><msup><mi mathvariant="normal">A</mi><mtext>2+x</mtext></msup></mrow>'];
        yield 'a subscript of a letter then a digit' => ['\\ce{A_{x2}}', '<mrow><msub><mi mathvariant="normal">A</mi><mtext>x2</mtext></msub></mrow>'];
        yield 'a subscript of a letter between digits' => ['\\ce{A_{x2y}}', '<mrow><msub><mi mathvariant="normal">A</mi><mtext>x2y</mtext></msub></mrow>'];
        yield 'a subscript of a digit, a letter, a digit' => ['\\ce{A_{2y3}}', '<mrow><msub><mi mathvariant="normal">A</mi><mtext>2y3</mtext></msub></mrow>'];
        yield 'a colour model with spaces' => ['\\textcolor[ HTML ]{FF8800}x', '<mrow><mstyle mathcolor="#ff8800"><mi>x</mi></mstyle></mrow>'];
        yield 'a tag with spaces' => ['x\\tag{ 1 }', '<mrow><mrow><mi>x</mi><mrow></mrow></mrow><mspace width="2em"></mspace><mtext>(1)</mtext></mrow>'];
        yield 'a macro whose body starts with a number' => ['\\newcommand{\\two}{2}\\two', '<mrow><mn>2</mn></mrow>'];
        yield 'one digit as an argument' => ['\\newcommand{\\f}[1]{[#1]}\\f5', '<mrow><mo>[</mo><mn>5</mn><mo>]</mo></mrow>'];
        yield 'a command as an argument' => ['\\newcommand{\\f}[1]{[#1]}\\f\\alpha', '<mrow><mo>[</mo><mi>α</mi><mo>]</mo></mrow>'];
        yield 'a number after a parameter in a text' => ['\\newcommand{\\f}[1]{#12}\\text{\\f a}', '<mrow><mtext>a2</mtext></mrow>'];
        yield 'the digits left by an argument in a text' => ['\\newcommand{\\f}[1]{#1}\\text{\\f12}', '<mrow><mtext>12</mtext></mrow>'];
        yield 'water' => ['\\ce{2H2 + O2 -> 2H2O}', '<mrow><mrow><mn>2</mn><msub><mi mathvariant="normal">H</mi><mn>2</mn></msub></mrow><mo>+</mo><msub><mi mathvariant="normal">O</mi><mn>2</mn></msub><mo stretchy="false">→</mo><mrow><mn>2</mn><msub><mi mathvariant="normal">H</mi><mn>2</mn></msub><mi mathvariant="normal">O</mi></mrow></mrow>'];
        yield 'charges' => ['\\ce{SO4^2- + Ba^{2+} -> BaSO4 v}', '<mrow><mrow><mi mathvariant="normal">S</mi><msubsup><mi mathvariant="normal">O</mi><mn>4</mn><mrow><mn>2</mn><mo>−</mo></mrow></msubsup></mrow><mo>+</mo><msup><mi>Ba</mi><mrow><mn>2</mn><mo>+</mo></mrow></msup><mo stretchy="false">→</mo><mrow><mi>Ba</mi><mi mathvariant="normal">S</mi><msub><mi mathvariant="normal">O</mi><mn>4</mn></msub></mrow><mo>↓</mo></mrow>'];
        yield 'ions' => ['\\ce{Na+ + Cl- <- NaCl}', '<mrow><msup><mi>Na</mi><mo>+</mo></msup><mo>+</mo><msup><mi>Cl</mi><mo>−</mo></msup><mo stretchy="false">←</mo><mrow><mi>Na</mi><mi>Cl</mi></mrow></mrow>'];
        yield 'equilibrium with conditions' => ['\\ce{N2 + 3H2 <=>[Fe][400 °C] 2NH3}', '<mrow><msub><mi mathvariant="normal">N</mi><mn>2</mn></msub><mo>+</mo><mrow><mn>3</mn><msub><mi mathvariant="normal">H</mi><mn>2</mn></msub></mrow><munderover><mo stretchy="true">⇌</mo><mtext>400 °C</mtext><mtext>Fe</mtext></munderover><mrow><mn>2</mn><mi mathvariant="normal">N</mi><msub><mi mathvariant="normal">H</mi><mn>3</mn></msub></mrow></mrow>'];
        yield 'states' => ['\\ce{NaCl(s) H2O(l) CO2(g) Na+(aq) C(cr)}', '<mrow><mrow><mi>Na</mi><mi>Cl</mi><mrow><mo>(</mo><mi mathvariant="normal">s</mi><mo>)</mo></mrow></mrow><mrow><msub><mi mathvariant="normal">H</mi><mn>2</mn></msub><mi mathvariant="normal">O</mi><mrow><mo>(</mo><mi mathvariant="normal">l</mi><mo>)</mo></mrow></mrow><mrow><mi mathvariant="normal">C</mi><msub><mi mathvariant="normal">O</mi><mn>2</mn></msub><mrow><mo>(</mo><mi mathvariant="normal">g</mi><mo>)</mo></mrow></mrow><mrow><msup><mi>Na</mi><mo>+</mo></msup><mrow><mo>(</mo><mi>aq</mi><mo>)</mo></mrow></mrow><mrow><mi mathvariant="normal">C</mi><mrow><mo>(</mo><mi>cr</mi><mo>)</mo></mrow></mrow></mrow>'];
        yield 'gas' => ['\\ce{CaCO3 -> CaO + CO2 ^}', '<mrow><mrow><mi>Ca</mi><mi mathvariant="normal">C</mi><msub><mi mathvariant="normal">O</mi><mn>3</mn></msub></mrow><mo stretchy="false">→</mo><mrow><mi>Ca</mi><mi mathvariant="normal">O</mi></mrow><mo>+</mo><mrow><mi mathvariant="normal">C</mi><msub><mi mathvariant="normal">O</mi><mn>2</mn></msub></mrow><mo>↑</mo></mrow>'];
        yield 'isotope' => ['\\ce{^{227}_{90}Th}', '<mrow><mmultiscripts><mi>Th</mi><mprescripts></mprescripts><mn>90</mn><mn>227</mn></mmultiscripts></mrow>'];
        yield 'mass number only' => ['\\ce{^{14}C}', '<mrow><mmultiscripts><mi mathvariant="normal">C</mi><mprescripts></mprescripts><mrow></mrow><mn>14</mn></mmultiscripts></mrow>'];
        yield 'atomic number only' => ['\\ce{_6C}', '<mrow><mmultiscripts><mi mathvariant="normal">C</mi><mprescripts></mprescripts><mn>6</mn><mrow></mrow></mmultiscripts></mrow>'];
        yield 'hydrate' => ['\\ce{CuSO4*5H2O} \\ce{CaSO4.H2O}', '<mrow><mrow><mi>Cu</mi><mi mathvariant="normal">S</mi><msub><mi mathvariant="normal">O</mi><mn>4</mn></msub><mo>⋅</mo><mn>5</mn><msub><mi mathvariant="normal">H</mi><mn>2</mn></msub><mi mathvariant="normal">O</mi></mrow><mrow><mi>Ca</mi><mi mathvariant="normal">S</mi><msub><mi mathvariant="normal">O</mi><mn>4</mn></msub><mo>⋅</mo><msub><mi mathvariant="normal">H</mi><mn>2</mn></msub><mi mathvariant="normal">O</mi></mrow></mrow>'];
        yield 'bonds' => ['\\ce{H2C=CH-C#N}', '<mrow><msub><mi mathvariant="normal">H</mi><mn>2</mn></msub><mi mathvariant="normal">C</mi><mo>=</mo><mi mathvariant="normal">C</mi><mi mathvariant="normal">H</mi><mo>−</mo><mi mathvariant="normal">C</mi><mo>≡</mo><mi mathvariant="normal">N</mi></mrow>'];
        yield 'bond to a group' => ['\\ce{C-(OH)}', '<mrow><mi mathvariant="normal">C</mi><mo>−</mo><mrow><mo>(</mo><mi mathvariant="normal">O</mi><mi mathvariant="normal">H</mi><mo>)</mo></mrow></mrow>'];
        yield 'groups' => ['\\ce{Ca(OH)2 [Cu(NH3)4]^2+}', '<mrow><mrow><mi>Ca</mi><msub><mrow><mo>(</mo><mi mathvariant="normal">O</mi><mi mathvariant="normal">H</mi><mo>)</mo></mrow><mn>2</mn></msub></mrow><msup><mrow><mo>[</mo><mi>Cu</mi><msub><mrow><mo>(</mo><mi mathvariant="normal">N</mi><msub><mi mathvariant="normal">H</mi><mn>3</mn></msub><mo>)</mo></mrow><mn>4</mn></msub><mo>]</mo></mrow><mrow><mn>2</mn><mo>+</mo></mrow></msup></mrow>'];
        yield 'variable subscript' => ['\\ce{Fe_xO}', '<mrow><msub><mi>Fe</mi><mi>x</mi></msub><mi mathvariant="normal">O</mi></mrow>'];
        yield 'braced subscript' => ['\\ce{C_{n}H_{2n}}', '<mrow><msub><mi mathvariant="normal">C</mi><mi>n</mi></msub><msub><mi mathvariant="normal">H</mi><mrow><mn>2</mn><mi>n</mi></mrow></msub></mrow>'];
        yield 'text charge' => ['\\ce{X^{ox}}', '<mrow><msup><mi mathvariant="normal">X</mi><mtext>ox</mtext></msup></mrow>'];
        yield 'electron' => ['\\ce{e-}', '<mrow><msup><mi>e</mi><mo>−</mo></msup></mrow>'];
        yield 'lowercase variable' => ['\\ce{x}', '<mrow><mi>x</mi></mrow>'];
        yield 'letter charge' => ['\\ce{A^x}', '<mrow><msup><mi mathvariant="normal">A</mi><mi>x</mi></msup></mrow>'];
        yield 'empty chemistry' => ['\\ce{}', '<mrow></mrow>'];
        yield 'lone number' => ['\\ce{42}', '<mrow><mn>42</mn></mrow>'];
    }

    #[DataProvider('formulas')]
    public function testConvertsEachConstruct(string $latex, string $mathml): void
    {
        self::assertSame($mathml, $this->body(new LatexToMathMl()->convert($latex, false)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalid(): iterable
    {
        yield 'recursive macro' => ['\\newcommand{\\a}{\\a}\\a', 'at byte 19: "\\a" is used inside its own expansion.'];
        yield 'mutually recursive macros' => ['\\def\\a{\\b}\\def\\b{x\\a} y\\a', 'at byte 23: "\\a" is used inside its own expansion.'];
        yield 'macro already defined' => ['\\newcommand{\\x}{a}\\newcommand{\\x}{b}', 'at byte 18: "\\x" is already defined; redefine it with "\\renewcommand".'];
        yield 'optional argument' => ['\\newcommand{\\x}[1][0]{#1}', 'at byte 18: a macro with an optional argument is not supported.'];
        yield 'arity not a digit' => ['\\newcommand{\\x}[a]{x}', 'at byte 16: the number of arguments of a macro is a digit between brackets, "[2]".'];
        yield 'arity of two digits' => ['\\newcommand{\\x}[12]{x}', 'at byte 16: the number of arguments of a macro is a digit between brackets, "[2]".'];
        yield 'arity not closed' => ['\\newcommand{\\x}[1{x}', 'at byte 16: the number of arguments of a macro is a digit between brackets, "[2]".'];
        yield 'parameter beyond the arity' => ['\\newcommand{\\x}[1]{#2}', 'at byte 19: "#" in the body of "\\x" is followed by the number of one of its 1 arguments.'];
        yield 'parameter without number' => ['\\newcommand{\\x}[1]{#a}', 'at byte 19: "#" in the body of "\\x" is followed by the number of one of its 1 arguments.'];
        yield 'parameter at the end of a body' => ['\\newcommand{\\x}[1]{a#}', 'at byte 20: "#" in the body of "\\x" is followed by the number of one of its 1 arguments.'];
        yield 'parameter zero' => ['\\newcommand{\\x}[1]{#0}', 'at byte 19: "#" in the body of "\\x" is followed by the number of one of its 1 arguments.'];
        yield 'def parameters out of order' => ['\\def\\x#2{}', 'at byte 7: the parameters of "\\def" are #1 to #9, in order and without delimiters; #1 comes next.'];
        yield 'delimited def' => ['\\def\\x#1.{#1}', 'at byte 8: the body of "\\x" is a group between braces.'];
        yield 'name reserved' => ['\\newcommand{\\begin}{a}', 'at byte 12: "\\newcommand" needs the name of a command made of letters, other than \\begin, \\end, \\left, \\right, \\middle and the definitions.'];
        yield 'name of a symbol' => ['\\def\\,{a}', 'at byte 4: "\\def" needs the name of a command made of letters, other than \\begin, \\end, \\left, \\right, \\middle and the definitions.'];
        yield 'name not a command' => ['\\newcommand{x}{a}', 'at byte 12: "\\newcommand" needs the name of a command made of letters, other than \\begin, \\end, \\left, \\right, \\middle and the definitions.'];
        yield 'two names' => ['\\newcommand{\\a\\b}{a}', 'at byte 0: "\\newcommand" names one command between braces.'];
        yield 'body without braces' => ['\\newcommand{\\a}x', 'at byte 15: the body of "\\a" is a group between braces.'];
        yield 'body never closed' => ['\\newcommand{\\a}{x', 'at byte 15: this group is never closed.'];
        yield 'definition at the end' => ['\\newcommand', 'at byte 11: "\\newcommand" needs the name of a command made of letters, other than \\begin, \\end, \\left, \\right, \\middle and the definitions.'];
        yield 'macro without its argument' => ['\\newcommand{\\f}[1]{#1}\\f', 'at byte 22: "\\f" needs an argument.'];
        yield 'macro cut by a brace' => ['{\\newcommand{\\f}[1]{#1}\\f}', 'at byte 23: "\\f" needs an argument.'];
        yield 'unknown colour' => ['\\color{chartreuse}x', 'at byte 0: the colour "chartreuse" is not a name LaTeX knows, "#rrggbb", "[HTML]{rrggbb}" nor "[rgb]{r,g,b}".'];
        yield 'colour model unknown' => ['\\textcolor[cmyk]{0,0,0,1}{x}', 'at byte 0: the colour "cmyk 0,0,0,1" is not a name LaTeX knows, "#rrggbb", "[HTML]{rrggbb}" nor "[rgb]{r,g,b}".'];
        yield 'HTML colour too short' => ['\\textcolor[HTML]{FFF}{x}', 'at byte 0: the colour "HTML FFF" is not a name LaTeX knows, "#rrggbb", "[HTML]{rrggbb}" nor "[rgb]{r,g,b}".'];
        yield 'rgb beyond one' => ['\\textcolor[rgb]{1.5,0,0}{x}', 'at byte 0: the colour "rgb 1.5,0,0" is not a name LaTeX knows, "#rrggbb", "[HTML]{rrggbb}" nor "[rgb]{r,g,b}".'];
        yield 'hexadecimal of five digits' => ['\\color{#12345}x', 'at byte 0: the colour "#12345" is not a name LaTeX knows, "#rrggbb", "[HTML]{rrggbb}" nor "[rgb]{r,g,b}".'];
        yield 'colour in an argument' => ['x^\\color{red}', 'at byte 2: "\\color" sets the colour of the rest of a group; the colour of an argument is written "\\textcolor{colour}{…}".'];
        yield 'colour model never closed' => ['\\textcolor[rgb', 'at byte 10: this colour model is never closed by "]".'];
        yield 'colour without argument' => ['\\textcolor', 'at byte 10: "\\textcolor" needs a group.'];
        yield 'script link' => ['\\href{javascript:alert(1)}{x}', 'at byte 0: "javascript:alert(1)" is not an http, https or mailto address.'];
        yield 'data link' => ['\\url{data:text/html,x}', 'at byte 0: "data:text/html,x" is not an http, https or mailto address.'];
        yield 'link to an IP address' => ['\\url{http://127.0.0.1/}', 'at byte 0: "http://127.0.0.1/" is not an http, https or mailto address.'];
        yield 'mail with two addresses' => ['\\url{mailto:a@b.org,c@d.org}', 'at byte 0: "mailto:a@b.org,c@d.org" is not an http, https or mailto address.'];
        yield 'mail to an IP address' => ['\\url{mailto:a@127.0.0.1}', 'at byte 0: "mailto:a@127.0.0.1" is not an http, https or mailto address.'];
        yield 'mail with a port' => ['\\url{mailto:a@example.org:25}', 'at byte 0: "mailto:a@example.org:25" is not an http, https or mailto address.'];
        yield 'mail with a long local part' => ['\\url{mailto:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa@example.org}', 'at byte 0: "mailto:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa@example" is not an http, https or mailto address.'];
        yield 'link without text' => ['\\href{https://example.com}', 'at byte 26: "\\href" needs a group.'];
        yield 'two tags' => ['x\\tag{1}\\tag{2}', 'at byte 8: a formula, or a row of an environment, has one "\\tag".'];
        yield 'two tags in a row' => ['\\begin{matrix}a\\tag{1}&b\\tag{2}\\end{matrix}', 'at byte 24: a formula, or a row of an environment, has one "\\tag".'];
        yield 'hline in a cell' => ['\\begin{matrix}a\\hline\\end{matrix}', 'at byte 15: "\\hline" is only allowed at the start of a row of an environment.'];
        yield 'hline outside' => ['\\hline', 'at byte 0: "\\hline" is only allowed at the start of a row of an environment.'];
        yield 'column specification' => ['\\begin{array}{p{2cm}}a\\end{array}', 'at byte 0: the columns of "array" are written with l, c, r and |.'];
        yield 'command in chemistry' => ['\\ce{A <->[h\\nu] B}', 'at byte 11: "\\nu" is not allowed in a text.'];
        yield 'unknown character in chemistry' => ['\\ce{H@}', 'at byte 5: "@" is not read in a chemical formula.'];
        yield 'group never closed in chemistry' => ['\\ce{Ca(OH}', 'at byte 6: this group of a chemical formula is never closed by ")".'];
        yield 'group cut by a space in chemistry' => ['\\ce{Ca(O H)}', 'at byte 6: this group of a chemical formula is never closed by ")".'];
        yield 'condition never closed' => ['\\ce{A ->[x}', 'at byte 8: the condition of an arrow is never closed by "]".'];
        yield 'empty charge' => ['\\ce{Fe^{}}', 'at byte 8: a script of a chemical formula is empty.'];
        yield 'script never closed' => ['\\ce{Fe^{2}', 'at byte 3: this group is never closed.'];
        yield 'prescript without element' => ['\\ce{^{14}x}', 'at byte 4: a mass or an atomic number is written before an element.'];
        yield 'bare script' => ['\\ce{Fe^}', 'at byte 6: a script of a chemical formula is empty.'];
        yield 'comment shifts nothing' => ['\\ce{A %c
@}', 'at byte 9: "@" is not read in a chemical formula.'];
        yield 'chemistry script never closed' => ['\\ce{Fe^\\{2}', 'at byte 7: this group of a chemical formula is never closed.'];
        yield 'a plus that is not a word' => ['\\ce{+a}', 'at byte 4: "+" is not read in a chemical formula.'];
        yield 'a plus after a space and before a letter' => ['\\ce{A +B}', 'at byte 6: "+" is not read in a chemical formula.'];
        yield 'an unknown character before others in chemistry' => ['\\ce{H@bc}', 'at byte 5: "@" is not read in a chemical formula.'];
        yield 'a script of spaces' => ['\\ce{Fe^{ }}', 'at byte 9: a script of a chemical formula is empty.'];
        yield 'a script without an element or a group' => ['\\ce{^_}', 'at byte 4: a script of a chemical formula is empty.'];
        yield 'an unbraced character that is not read' => ['\\ce@', 'at byte 3: "@" is not read in a chemical formula.'];
        yield 'a macro without parameters using one' => ['\\newcommand{\\x}{#1}', 'at byte 16: "#" in the body of "\\x" is followed by the number of one of its 0 arguments.'];
        yield 'a digit left by a macro argument' => ['\\def\\f#1{}\\ce{(\\f 12)}', 'at byte 19: "2" is not read in a chemical formula.'];
        yield 'an address with spaces' => ['\\url{ javascript:x }', 'at byte 0: "javascript:x" is not an http, https or mailto address.'];
        yield 'a long colour with a multibyte tail' => ['\\color{x'.str_repeat("\u{E9}", 49).'}y', 'at byte 0: the colour "x'.str_repeat("\u{E9}", 39).'" is not a name LaTeX knows, "#rrggbb", "[HTML]{rrggbb}" nor "[rgb]{r,g,b}".'];
        yield 'a long address of multibyte letters' => ['\\url{'.str_repeat("\u{E9}", 100).'}', 'at byte 0: "'.str_repeat("\u{E9}", 80).'" is not an http, https or mailto address.'];
    }

    #[DataProvider('invalid')]
    public function testRefusesWhatItCannotConvert(string $latex, string $message): void
    {
        try {
            new LatexToMathMl()->convert($latex);
            self::fail('The formula is refused.');
        } catch (InvalidLatex $invalidLatex) {
            self::assertSame('The formula cannot be converted '.$message, $invalidLatex->getMessage());
            self::assertSame((int) substr($message, 8), $invalidLatex->offset);
        }
    }

    public function testKeepsTheDefinitionsInTheAnnotation(): void
    {
        self::assertStringContainsString(
            '<annotation encoding="application/x-tex">\\newcommand{\\R}{\\mathbb{R}}\\R</annotation>',
            new LatexToMathMl()->convert('\\newcommand{\\R}{\\mathbb{R}}\\R'),
        );
    }

    public function testBoundsTheExpansions(): void
    {
        $bomb = '\\def\\a{x}\\def\\b{\\a\\a\\a\\a}\\def\\c{\\b\\b\\b\\b}\\def\\d{\\c\\c\\c\\c}';

        self::assertSame(64, substr_count(new LatexToMathMl(1000, 64, 85)->convert($bomb.'\\d'), '<mi>x</mi>'));
        $this->assertRefused(new LatexToMathMl(1000, 64, 84), $bomb.'\\d', 'at byte 57: the formula expands more than 84 macros.');
        $this->assertRefused(new LatexToMathMl(100_000, 64, 1000), $bomb.str_repeat('\\d', 12), 'at byte 79: the formula expands more than 1000 macros.');
    }

    public function testBoundsTheTokensOfTheExpansion(): void
    {
        $formula = '\\def\\a{xxxxxxxxxx}\\a\\a';

        self::assertSame(20, substr_count(new LatexToMathMl(36)->convert($formula), '<mi>x</mi>'));
        $this->assertRefused(new LatexToMathMl(35), $formula, 'at byte 20: the formula expands to more than 35 tokens.');
        self::assertSame(30, substr_count(new LatexToMathMl(55)->convert('\\def\\a#1{#1#1#1}\\a{xxxxxxxxxx}'), '<mi>x</mi>'));
        $this->assertRefused(new LatexToMathMl(54), '\\def\\a#1{#1#1#1}\\a{xxxxxxxxxx}', 'at byte 16: the formula expands to more than 54 tokens.');
    }

    public function testBoundsTheNestingOfMacros(): void
    {
        $formula = '\\def\\a{x}\\def\\b{\\a}\\def\\c{\\b}\\c';

        self::assertStringContainsString('<mi>x</mi>', new LatexToMathMl(1000, 3)->convert($formula));
        $this->assertRefused(new LatexToMathMl(1000, 2), $formula, 'at byte 29: the macros nest deeper than 2 levels.');
    }

    public function testDefaultBudgetOfExpansions(): void
    {
        self::assertSame(1000, substr_count(new LatexToMathMl()->convert('\\def\\a{x}'.str_repeat('\\a', 1000)), '<mi>x</mi>'));
        $this->assertRefused(new LatexToMathMl(), '\\def\\a{x}'.str_repeat('\\a', 1001), 'at byte 2009: the formula expands more than 1000 macros.');
    }

    public function testReadsColours(): void
    {
        self::assertSame('#bf8040', Colors::hex(' brown ', null));
        self::assertSame('#aabbcc', Colors::hex('#ABC', null));
        self::assertSame('#a1b2c3', Colors::hex('#A1B2C3', null));
        self::assertSame('#a1b2c3', Colors::hex('A1B2C3', 'HTML'));
        self::assertSame('#000000', Colors::hex('0,0,0', 'rgb'));
        self::assertSame('#ffffff', Colors::hex('1,1,1', 'rgb'));
        self::assertSame('#808080', Colors::hex('0.5,.5,0.50', 'rgb'));

        foreach ([['Red', null], ['#abcd', null], ['abc', null], ['#abcdeg', null], ['#ABC', 'HTML'], ['A1B2C', 'HTML'], ['1,1', 'rgb'], ['1,1,1,1', 'rgb'], ['2,0,0', 'rgb'], ['-1,0,0', 'rgb'], ['1.1,0,0', 'rgb'], ['red', 'rgb'], ['red', 'named']] as [$color, $model]) {
            self::assertNull(Colors::hex($color, $model), $color);
        }
    }

    public function testReadsColoursOnlyWhenTheyAreWholly(): void
    {
        self::assertNull(Colors::hex('x#abc', null));
        self::assertNull(Colors::hex('#abcx', null));
        self::assertNull(Colors::hex('zA1B2C3', 'HTML'));
        self::assertNull(Colors::hex('A1B2C3z', 'HTML'));
    }

    public function testRoundsEachChannelOfAnRgbColour(): void
    {
        self::assertSame('#000000', Colors::hex('0.001,0.001,0.001', 'rgb'));
        self::assertSame('#010203', Colors::hex('0.004,0.008,0.012', 'rgb'));
    }

    public function testNamesTheWholeCharacterItDoesNotRead(): void
    {
        // The test of the case of a byte depends on the locale; the formula is read in the one every machine has.
        $locale = setlocale(\LC_CTYPE, '0');
        setlocale(\LC_CTYPE, 'C');

        try {
            new LatexToMathMl()->convert("\\ce{H\u{20AC}xyz}");
            self::fail('The character is refused.');
        } catch (InvalidLatex $invalidLatex) {
            self::assertSame('The formula cannot be converted at byte 5: "€" is not read in a chemical formula.', $invalidLatex->getMessage());
        } finally {
            setlocale(\LC_CTYPE, false === $locale ? 'C' : $locale);
        }
    }

    public function testReadsAddressesWithSpaces(): void
    {
        self::assertSame('mailto:a@example.org', Link::href(' mailto:a@example.org '));
        self::assertSame('https://example.org/', Link::href("\n https://example.org \t"));
    }

    public function testChemistryEscapesTheTextItWrites(): void
    {
        self::assertSame(
            '<mrow><mi mathvariant="normal">A</mi><mover><mo stretchy="true">→</mo><mtext>a&apos;b&quot;c&lt;d&amp;e&gt;f</mtext></mover><mi mathvariant="normal">B</mi></mrow>',
            new Chemistry()->convert("A ->[a'b\"c<d&e>f] B", [], 0),
        );
        self::assertSame(
            "<mrow><mi mathvariant=\"normal\">A</mi><mover><mo stretchy=\"true\">→</mo><mtext>a\u{FFFD}b</mtext></mover><mi mathvariant=\"normal\">B</mi></mrow>",
            new Chemistry()->convert("A ->[a\xFFb] B", [], 0),
        );
    }

    public function testMacrosExpandNoTokensToTheEnd(): void
    {
        $tokens = new Macros(8, 8, 8)->expand([]);

        self::assertCount(1, $tokens);
        self::assertTrue($tokens[0]->is(Token::END));
        self::assertSame(0, $tokens[0]->offset);
    }

    public function testReadsAddresses(): void
    {
        self::assertSame('https://example.com/', Link::href(' HTTPS://EXAMPLE.com '));
        self::assertSame('mailto:first.last+tag@xn--bcher-kva.example', Link::href('MAILTO:first.last+tag@bücher.example'));
        self::assertSame('mailto:'.str_repeat('a', 64).'@example.org', Link::href('mailto:'.str_repeat('a', 64).'@example.org'));

        foreach (['ftp://example.com/', 'mailto:'.str_repeat('a', 65).'@example.org', 'mailto:.a@example.org', 'mailto:a..b@example.org', 'mailto:a@example.org?subject=x', 'mailto:a@localhost', 'mailto:@example.org', 'xmailto:a@example.org', 'mailto:a@b@example.org', 'https://user:pw@example.com/'] as $address) {
            self::assertNull(Link::href($address), $address);
        }
    }

    public function testChemistryAtTheEndOfItsText(): void
    {
        try {
            new Chemistry()->convert('A(', [], 42);
            self::fail('The group is refused.');
        } catch (InvalidLatex $invalidLatex) {
            self::assertSame(42, $invalidLatex->offset);
        }
    }

    private function assertRefused(LatexToMathMl $converter, string $latex, string $message): void
    {
        try {
            $converter->convert($latex);
            self::fail($latex.' is refused.');
        } catch (InvalidLatex $invalidLatex) {
            self::assertSame('The formula cannot be converted '.$message, $invalidLatex->getMessage());
        }
    }

    private function body(string $math): string
    {
        return (string) preg_replace('#^<math[^>]*><semantics>(.*)<annotation.*$#s', '$1', $math);
    }
}
