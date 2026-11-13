<?php

declare(strict_types=1);

namespace Phil\LatexMathMl\Internal;

use Phil\LatexMathMl\Exception\InvalidLatex;

/**
 * The text of `\ce{…}`, the chemical notation: words separated by spaces, each a formula, `+`, an arrow with its
 * conditions, or a gas `^` or a precipitate `v`. In a formula, elements are upright, the digits after an atom or a
 * group are its subscript, a final `+` or `-` and what follows `^` are its charge, a leading number its coefficient,
 * `(s)`, `(l)`, `(g)`, `(aq)` and `(cr)` its state, `^{A}_{Z}` before an element its mass and atomic numbers, `*` or
 * `.` a hydrate's dot, `-`, `=` and `#` between atoms single, double and triple bonds.
 *
 * Every character is read once; any other is refused with its position.
 *
 * @internal
 */
final class Chemistry
{
    /** The arrows, longest first so that `<=>` is not read as `<=`. */
    private const array ARROWS = ['<=>' => '⇌', '<->' => '↔', '->' => '→', '<-' => '←'];

    private const array STATES = ['s', 'l', 'g', 'aq', 'cr'];

    private const array BONDS = ['-' => '−', '=' => '=', '#' => '≡'];

    private string $text = '';

    private int $position = 0;

    private int $length = 0;

    /** @var list<int> */
    private array $offsets = [];

    private int $fallback = 0;

    /**
     * @param list<int> $offsets the byte of the formula each byte of the text comes from
     * @param int       $at      the byte of `\ce`, for a problem at the end of the text
     */
    public function convert(string $text, array $offsets, int $at): string
    {
        $this->text = $text;
        $this->offsets = $offsets;
        $this->fallback = $at;
        $this->length = \strlen($text);
        $this->position = 0;
        $nodes = [];

        while (true) {
            while ($this->position < $this->length && ctype_space($this->text[$this->position])) {
                ++$this->position;
            }

            if ($this->position >= $this->length) {
                return 1 === \count($nodes) ? $nodes[0] : '<mrow>'.implode('', $nodes).'</mrow>';
            }

            $nodes[] = $this->word();
        }
    }

    private function word(): string
    {
        foreach (self::ARROWS as $arrow => $character) {
            if (substr($this->text, $this->position, \strlen($arrow)) === $arrow) {
                $this->position += \strlen($arrow);

                return $this->arrow($character);
            }
        }

        $single = $this->wordIs('+') ? '<mo>+</mo>' : ($this->wordIs('^') ? '<mo>↑</mo>' : ($this->wordIs('v') ? '<mo>↓</mo>' : null));

        if (null !== $single) {
            ++$this->position;

            return $single;
        }

        return $this->formula();
    }

    /**
     * Whether the word at the cursor is this one character alone.
     */
    private function wordIs(string $character): bool
    {
        return $this->peek() === $character && ($this->position + 1 >= $this->length || ctype_space($this->text[$this->position + 1]));
    }

    /**
     * An arrow, with the conditions written over it and under it between brackets: `->[heat]`, `->[H2O][25 °C]`.
     */
    private function arrow(string $arrow): string
    {
        $over = $this->condition();
        $under = null === $over ? null : $this->condition();
        $operator = \sprintf('<mo stretchy="%s">%s</mo>', null === $over ? 'false' : 'true', $arrow);

        return match (true) {
            null !== $under => \sprintf('<munderover>%s%s%s</munderover>', $operator, $under, $over),
            null !== $over => \sprintf('<mover>%s%s</mover>', $operator, $over),
            default => $operator,
        };
    }

    private function condition(): ?string
    {
        if ('[' !== $this->peek()) {
            return null;
        }

        $close = strpos($this->text, ']', $this->position);

        if (false === $close) {
            throw InvalidLatex::at($this->offset($this->position), 'the condition of an arrow is never closed by "]"');
        }

        $condition = substr($this->text, $this->position + 1, $close - $this->position - 1);
        $this->position = $close + 1;

        return '<mtext>'.$this->escape($condition).'</mtext>';
    }

    /**
     * A formula: a coefficient, then atoms, groups, bonds and dots, up to a space or the end.
     */
    private function formula(): string
    {
        $nodes = [];
        $coefficient = $this->digits();

        if ('' !== $coefficient) {
            $nodes[] = '<mn>'.$coefficient.'</mn>';
        }

        while ($this->position < $this->length && !ctype_space($this->text[$this->position])) {
            $nodes[] = $this->part();
        }

        return 1 === \count($nodes) ? $nodes[0] : '<mrow>'.implode('', $nodes).'</mrow>';
    }

    /**
     * One part of a formula, with its subscript and its charge.
     */
    private function part(): string
    {
        $character = $this->text[$this->position];

        if ('^' === $character || '_' === $character) {
            return $this->isotope();
        }

        if ('*' === $character || '.' === $character) {
            ++$this->position;
            $coefficient = $this->digits();

            return '<mo>⋅</mo>'.('' === $coefficient ? '' : '<mn>'.$coefficient.'</mn>');
        }

        if (isset(self::BONDS[$character]) && $this->bondFollows()) {
            ++$this->position;

            return '<mo>'.self::BONDS[$character].'</mo>';
        }

        return $this->scripted($this->atom());
    }

    /**
     * Whether a bond is at the cursor: `-`, `=` or `#` followed by an element or a group, never a final charge.
     */
    private function bondFollows(): bool
    {
        $next = $this->text[$this->position + 1] ?? '';

        return ctype_upper($next) || '(' === $next;
    }

    /**
     * An element, a variable in lowercase, a group between parentheses or brackets, or a state.
     */
    private function atom(): string
    {
        $character = $this->text[$this->position];

        if (ctype_upper($character)) {
            $symbol = $character;
            ++$this->position;

            while ($this->position < $this->length && ctype_lower($this->text[$this->position])) {
                $symbol .= $this->text[$this->position++];
            }

            return 1 === \strlen($symbol) ? '<mi mathvariant="normal">'.$symbol.'</mi>' : '<mi>'.$symbol.'</mi>';
        }

        if (ctype_lower($character)) {
            ++$this->position;

            return '<mi>'.$character.'</mi>';
        }

        if ('(' === $character || '[' === $character) {
            return $this->group($character, '(' === $character ? ')' : ']');
        }

        throw InvalidLatex::at($this->offset($this->position), \sprintf('"%s" is not read in a chemical formula', mb_substr(substr($this->text, $this->position), 0, 1)));
    }

    private function group(string $open, string $close): string
    {
        $start = $this->position;
        $end = strpos($this->text, $close, $start);
        $inner = false === $end ? '' : substr($this->text, $start + 1, $end - $start - 1);

        if ('(' === $open && \in_array($inner, self::STATES, true)) {
            $this->position = $end + 1;

            return '<mrow><mo>(</mo><mi'.(1 === \strlen($inner) ? ' mathvariant="normal"' : '').'>'.$inner.'</mi><mo>)</mo></mrow>';
        }

        ++$this->position;
        $nodes = [];

        while ($this->peek() !== $close) {
            if ($this->position >= $this->length || ctype_space($this->text[$this->position])) {
                throw InvalidLatex::at($this->offset($start), \sprintf('this group of a chemical formula is never closed by "%s"', $close));
            }

            $nodes[] = $this->part();
        }

        ++$this->position;

        return \sprintf('<mrow><mo>%s</mo>%s<mo>%s</mo></mrow>', $open, implode('', $nodes), $close);
    }

    /**
     * The atom with the digits after it as its subscript, `_` and its argument too, and its charge: what follows `^`,
     * or a final `+` or `-`.
     */
    private function scripted(string $atom): string
    {
        $sub = $this->digits();

        if ('' === $sub && '_' === $this->peek()) {
            ++$this->position;
            $sub = $this->braced();
        }

        $charge = null;

        if ('^' === $this->peek()) {
            ++$this->position;
            $charge = $this->charge($this->braced());
        } elseif ('+' === $this->peek() || '-' === $this->peek() && !$this->bondFollows()) {
            $charge = $this->charge($this->text[$this->position++]);
        }

        $sub = '' === $sub ? null : $this->script($sub);

        return match (true) {
            null !== $charge && null !== $sub => \sprintf('<msubsup>%s%s%s</msubsup>', $atom, $sub, $charge),
            null !== $charge => \sprintf('<msup>%s%s</msup>', $atom, $charge),
            null !== $sub => \sprintf('<msub>%s%s</msub>', $atom, $sub),
            default => $atom,
        };
    }

    /**
     * `^{A}_{Z}X`: an element with its mass number and its atomic number before it.
     */
    private function isotope(): string
    {
        $start = $this->position;
        $mass = '<mrow></mrow>';
        $number = '<mrow></mrow>';

        while ('^' === $this->peek() || '_' === $this->peek()) {
            $kind = $this->text[$this->position++];
            $value = $this->script($this->braced());
            '^' === $kind ? $mass = $value : $number = $value;
        }

        if (!ctype_upper($this->peek())) {
            throw InvalidLatex::at($this->offset($start), 'a mass or an atomic number is written before an element');
        }

        return \sprintf('<mmultiscripts>%s<mprescripts></mprescripts>%s%s</mmultiscripts>', $this->atom(), $number, $mass);
    }

    /**
     * The argument of `^` or `_`: a group between braces, one letter, or the digits and the sign after them.
     */
    private function braced(): string
    {
        if ('{' === $this->peek()) {
            $close = strpos($this->text, '}', $this->position);

            if (false === $close) {
                throw InvalidLatex::at($this->offset($this->position), 'this group of a chemical formula is never closed');
            }

            $value = substr($this->text, $this->position + 1, $close - $this->position - 1);
            $this->position = $close + 1;
        } else {
            $value = $this->digits();

            if ('' === $value && ctype_alpha($this->peek())) {
                $value = $this->text[$this->position++];
            } elseif ('+' === $this->peek() || '-' === $this->peek()) {
                $value .= $this->text[$this->position++];
            }
        }

        if ('' === trim($value)) {
            throw InvalidLatex::at($this->offset(max(0, $this->position - 1)), 'a script of a chemical formula is empty');
        }

        return $value;
    }

    /**
     * A charge, `2+`, `-`, `3-`, or a text: the number and the sign each as they are written, the minus a minus sign.
     */
    private function charge(string $charge): string
    {
        if (1 === preg_match('/^([0-9]*)([+-])$/', $charge, $match)) {
            $sign = '<mo>'.('-' === $match[2] ? '−' : '+').'</mo>';

            return '' === $match[1] ? $sign : '<mrow><mn>'.$match[1].'</mn>'.$sign.'</mrow>';
        }

        return $this->script($charge);
    }

    private function script(string $value): string
    {
        return match (true) {
            1 === preg_match('/^[0-9]+$/', $value) => '<mn>'.$value.'</mn>',
            1 === preg_match('/^[A-Za-z]$/', $value) => '<mi>'.$value.'</mi>',
            1 === preg_match('/^([0-9]+)([A-Za-z])$/', $value, $match) => '<mrow><mn>'.$match[1].'</mn><mi>'.$match[2].'</mi></mrow>',
            default => '<mtext>'.$this->escape($value).'</mtext>',
        };
    }

    private function digits(): string
    {
        $start = $this->position;

        while ($this->position < $this->length && ctype_digit($this->text[$this->position])) {
            ++$this->position;
        }

        return substr($this->text, $start, $this->position - $start);
    }

    private function peek(): string
    {
        return $this->text[$this->position] ?? '';
    }

    private function offset(int $position): int
    {
        return $this->offsets[$position] ?? $this->fallback;
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_XML1, 'UTF-8');
    }
}
