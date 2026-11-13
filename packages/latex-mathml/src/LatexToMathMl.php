<?php

declare(strict_types=1);

namespace Phil\LatexMathMl;

use Phil\LatexMathMl\Exception\InvalidLatex;
use Phil\LatexMathMl\Internal\Lexer;
use Phil\LatexMathMl\Internal\Macros;
use Phil\LatexMathMl\Internal\Parser;

/**
 * Converts a LaTeX formula to MathML Core, the MathML that browsers draw: identifiers, numbers and operators, scripts
 * and limits, fractions, roots, accents, fonts, stretched delimiters, matrices and aligned equations. The formula is
 * kept in a TeX annotation beside the MathML, so a reader can copy it.
 *
 * The set of commands is the common one of formulas written by hand, with simple macros, colours, links, boxes,
 * equation numbers and chemical notation; any other command is refused with its position, never passed through. The
 * output holds only MathML elements, the HTML link of `\href` inside a text, and the attributes this class writes,
 * every text escaped and every address and colour checked, so it is safe to write in a page. Length, depth and the
 * expansion of macros are bounded.
 */
final readonly class LatexToMathMl
{
    /**
     * @param int $maxLength     the longest formula accepted, in bytes; the expanded formula holds as many tokens at most
     * @param int $maxDepth      the deepest nesting of groups, arguments, environments and macros
     * @param int $maxExpansions the most uses of macros expanded in a formula
     */
    public function __construct(
        private int $maxLength = 20_000,
        private int $maxDepth = 64,
        private int $maxExpansions = 1_000,
    ) {
    }

    /**
     * The `<math>` element of the formula: `display="block"` for a formula on its own line, inline otherwise. The
     * delimiters `$…$`, `$$…$$`, `\(…\)` and `\[…\]` around the whole formula are left out.
     *
     * @throws InvalidLatex
     */
    public function convert(string $latex, bool $display = true): string
    {
        if (\strlen($latex) > $this->maxLength) {
            throw InvalidLatex::at($this->maxLength, \sprintf('the formula is longer than %d bytes', $this->maxLength));
        }

        $formula = $this->undelimited(trim($latex));
        $tokens = new Macros($this->maxDepth, $this->maxExpansions, $this->maxLength)->expand(Lexer::tokens($formula));
        $body = new Parser($tokens, $this->maxDepth)->parse();

        return \sprintf(
            '<math xmlns="http://www.w3.org/1998/Math/MathML"%s><semantics>%s<annotation encoding="application/x-tex">%s</annotation></semantics></math>',
            $display ? ' display="block"' : '',
            str_starts_with($body, '<mrow>') ? $body : '<mrow>'.$body.'</mrow>',
            htmlspecialchars($formula, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_XML1, 'UTF-8'),
        );
    }

    private function undelimited(string $latex): string
    {
        foreach ([['$$', '$$'], ['\\[', '\\]'], ['\\(', '\\)'], ['$', '$']] as [$open, $close]) {
            if (\strlen($latex) >= \strlen($open) + \strlen($close) && str_starts_with($latex, $open) && str_ends_with($latex, $close)) {
                return trim(substr($latex, \strlen($open), -\strlen($close)));
            }
        }

        return $latex;
    }
}
