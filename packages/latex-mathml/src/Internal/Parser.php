<?php

declare(strict_types=1);

namespace Phil\LatexMathMl\Internal;

use Closure;
use Phil\LatexMathMl\Exception\InvalidLatex;

/**
 * A recursive descent over the tokens of a formula that writes MathML as it reads: each atom becomes one element,
 * scripts wrap the atom before them, groups and environments nest. The depth of nesting is bounded, and every text
 * written is escaped.
 *
 * @internal
 */
final class Parser
{
    /** Stop at the closing brace of a group. */
    private const string IN_GROUP = 'group';

    /** Stop at the `]` of an optional argument. */
    private const string IN_BRACKET = 'bracket';

    /** Stop at `\right` and `\middle`. */
    private const string IN_FENCE = 'fence';

    /** Stop at `&`, `\\` and `\end`. */
    private const string IN_CELL = 'cell';

    /** The sizes of `\big` and its kin, in em. */
    private const array SIZES = ['big' => '1.2', 'Big' => '1.623', 'bigg' => '2.047', 'Bigg' => '2.470'];

    /**
     * The commands that set the style or the size of the rest of their group, and the attributes of its `mstyle`.
     */
    private const array SWITCHES = [
        'displaystyle' => 'displaystyle="true"', 'textstyle' => 'displaystyle="false"',
        'scriptstyle' => 'displaystyle="false" scriptlevel="1"', 'scriptscriptstyle' => 'displaystyle="false" scriptlevel="2"',
        'tiny' => 'mathsize="0.5em"', 'scriptsize' => 'mathsize="0.7em"', 'footnotesize' => 'mathsize="0.8em"',
        'small' => 'mathsize="0.9em"', 'normalsize' => 'mathsize="1em"', 'large' => 'mathsize="1.2em"',
        'Large' => 'mathsize="1.44em"', 'LARGE' => 'mathsize="1.728em"', 'huge' => 'mathsize="2.074em"',
        'Huge' => 'mathsize="2.488em"',
    ];

    /** The line of a box, and of the rules of an environment. */
    private const string LINE = '0.06em solid';

    /** The cells a matrix may hold. */
    private const int MAX_CELLS = 2500;

    private const string APPLY = '<mo>&#x2061;</mo>';

    private int $position = 0;

    private int $depth = 0;

    private string $font = '';

    /** The number of the formula, or of the row of an environment being read, once `\tag` has given it. */
    private ?string $tag = null;

    /**
     * @param array<int, Token> $tokens the tokens, ended by one of type `end`
     */
    public function __construct(
        private array $tokens,
        private readonly int $maxDepth,
    ) {
    }

    /**
     * The MathML of the whole formula, as one element.
     *
     * @throws InvalidLatex
     */
    public function parse(): string
    {
        $body = self::row($this->expression(null));

        return null === $this->tag ? $body : '<mrow>'.$body.'<mspace width="2em"></mspace>'.$this->tag.'</mrow>';
    }

    /**
     * The atoms up to the end of the formula or to what closes the context.
     *
     * @return list<string>
     */
    private function expression(?string $context): array
    {
        $nodes = [];

        while (true) {
            $token = $this->peekSolid();

            if ($token->is(Token::END)
                || self::IN_GROUP === $context && $token->is(Token::CLOSE)
                || self::IN_BRACKET === $context && $token->is(Token::CHAR, ']')
                || self::IN_FENCE === $context && ($token->is(Token::COMMAND, 'right') || $token->is(Token::COMMAND, 'middle'))
                || self::IN_CELL === $context && ($token->is(Token::ALIGN) || $token->is(Token::COMMAND, '\\') || $token->is(Token::COMMAND, 'end'))) {
                return $nodes;
            }

            $switch = $this->switched($token);

            if (null !== $switch) {
                $nodes[] = \sprintf('<mstyle %s>%s</mstyle>', $switch, self::row($this->expression($context)));

                return $nodes;
            }

            array_push($nodes, ...$this->scripted());
        }
    }

    /**
     * The attributes of the `mstyle` that a command setting the style, the size or the colour of the rest of its group
     * opens, the command read; null for any other token.
     */
    private function switched(Token $token): ?string
    {
        if (!$token->is(Token::COMMAND) || !isset(self::SWITCHES[$token->value]) && 'color' !== $token->value) {
            return null;
        }

        ++$this->position;

        return self::SWITCHES[$token->value] ?? 'mathcolor="'.$this->color($token).'"';
    }

    /**
     * An atom with its scripts, and the application of a function after it.
     *
     * @return list<string>
     */
    private function scripted(): array
    {
        $token = $this->peekSolid();
        [$base, $kind] = $token->is(Token::SUP) || $token->is(Token::SUB) ? ['<mrow></mrow>', 'normal'] : $this->atom();
        [$sup, $sub, $kind] = $this->scripts($kind);
        $under = 'large' === $kind || 'limit' === $kind;
        $node = match (true) {
            null !== $sup && null !== $sub => \sprintf($under ? '<munderover>%s%s%s</munderover>' : '<msubsup>%s%s%s</msubsup>', $base, $sub, $sup),
            null !== $sup => \sprintf($under ? '<mover>%s%s</mover>' : '<msup>%s%s</msup>', $base, $sup),
            null !== $sub => \sprintf($under ? '<munder>%s%s</munder>' : '<msub>%s%s</msub>', $base, $sub),
            default => $base,
        };

        return 'function' === $kind || 'limit' === $kind ? [$node, self::APPLY] : [$node];
    }

    /**
     * The superscript and the subscript after an atom — primes, `^`, `_` —, and its kind, which `\limits` and
     * `\nolimits` change.
     *
     * @return array{?string, ?string, string}
     */
    private function scripts(string $kind): array
    {
        $sup = null;
        $sub = null;

        while (true) {
            $token = $this->peekSolid();

            if ($token->is(Token::COMMAND, 'limits') || $token->is(Token::COMMAND, 'nolimits')) {
                ++$this->position;
                $kind = 'limits' === $token->value ? 'large' : 'normal';
            } elseif ($token->is(Token::CHAR, "'")) {
                $sup = $this->once($sup, '<mo>'.$this->primes().'</mo>', $token);
            } elseif ($token->is(Token::SUP)) {
                ++$this->position;
                $argument = $this->argument($token);
                $sup = null !== $sup && str_starts_with($sup, '<mo>′') ? '<mrow>'.$sup.$argument.'</mrow>' : $this->once($sup, $argument, $token);
            } elseif ($token->is(Token::SUB)) {
                ++$this->position;
                $sub = $this->once($sub, $this->argument($token), $token);
            } else {
                return [$sup, $sub, $kind];
            }
        }
    }

    private function primes(): string
    {
        $primes = '';

        while ($this->peek()->is(Token::CHAR, "'")) {
            ++$this->position;
            $primes .= '′';
        }

        return $primes;
    }

    /**
     * One atom, as one element, with its kind: `normal`, `large` for an operator whose limits go under and over,
     * `function` for a function applied to what follows, `limit` for one whose limits go under.
     *
     * @return array{string, string}
     */
    private function atom(): array
    {
        $token = $this->next();

        return match ($token->type) {
            Token::NUMBER => [$this->number($token->value), 'normal'],
            Token::LETTER => [$this->identifier($token->value), 'normal'],
            Token::CHAR => [$this->character($token), 'normal'],
            Token::OPEN => [$this->group($token), 'normal'],
            Token::COMMAND => $this->command($token),
            default => throw InvalidLatex::at($token->offset, match ($token->type) {
                Token::CLOSE => 'this closing brace opens nothing', Token::ALIGN => 'a column separator "&" is only allowed in an environment', default => 'a value is expected here',
            }),
        };
    }

    /**
     * @return array{string, string}
     */
    private function command(Token $token): array
    {
        $name = $token->value;

        return match (true) {
            isset(Symbols::IDENTIFIERS[$name]) => ['<mi>'.Symbols::IDENTIFIERS[$name].'</mi>', 'normal'],
            isset(Symbols::UPRIGHT[$name]) => ['<mi mathvariant="normal">'.Symbols::UPRIGHT[$name].'</mi>', 'normal'],
            isset(Symbols::OPERATORS[$name]) => ['<mo>'.$this->escape(Symbols::OPERATORS[$name]).'</mo>', 'normal'],
            isset(Symbols::FENCES[$name]) => ['<mo>'.Symbols::FENCES[$name].'</mo>', 'normal'],
            isset(Symbols::LARGE[$name]) => ['<mo>'.Symbols::LARGE[$name].'</mo>', 'large'],
            isset(Symbols::INTEGRALS[$name]) => ['<mo>'.Symbols::INTEGRALS[$name].'</mo>', 'normal'],
            \in_array($name, Symbols::FUNCTIONS, true) => ['<mi>'.$name.'</mi>', 'function'],
            \in_array($name, Symbols::LIMITS, true) => ['<mo movablelimits="true">'.$name.'</mo>', 'limit'],
            isset(Symbols::SPACES[$name]) => ['<mspace width="'.Symbols::SPACES[$name].'em"></mspace>', 'normal'],
            isset(Symbols::ACCENTS[$name]) => [\sprintf('<mover accent="true">%s<mo stretchy="%s">%s</mo></mover>', $this->argument($token), Symbols::ACCENTS[$name][1] ? 'true' : 'false', $this->escape(Symbols::ACCENTS[$name][0])), str_ends_with($name, 'brace') ? 'large' : 'normal'],
            isset(Symbols::UNDER[$name]) => [\sprintf('<munder accentunder="true">%s<mo stretchy="true">%s</mo></munder>', $this->argument($token), Symbols::UNDER[$name]), 'underbrace' === $name ? 'large' : 'normal'],
            isset(Symbols::FONTS[$name]) => [$this->font($token, Symbols::FONTS[$name]), 'normal'],
            isset(self::SIZES[$name]) || isset(self::SIZES[substr($name, 0, -1)]) => [$this->sized($token), 'normal'],
            default => $this->structure($token),
        };
    }

    /**
     * @return array{string, string}
     */
    private function structure(Token $token): array
    {
        return match ($token->value) {
            'frac', 'cfrac' => [\sprintf('<mfrac>%s%s</mfrac>', $this->argument($token), $this->argument($token)), 'normal'],
            'dfrac', 'tfrac' => [\sprintf('<mstyle displaystyle="%s"><mfrac>%s%s</mfrac></mstyle>', 'dfrac' === $token->value ? 'true' : 'false', $this->argument($token), $this->argument($token)), 'normal'],
            'binom', 'dbinom', 'tbinom' => [\sprintf('<mrow><mo>(</mo><mfrac linethickness="0">%s%s</mfrac><mo>)</mo></mrow>', $this->argument($token), $this->argument($token)), 'normal'],
            'sqrt' => [$this->root($token), 'normal'],
            'overset', 'stackrel' => [vsprintf('<mover>%2$s%1$s</mover>', [$this->argument($token), $this->argument($token)]), 'normal'],
            'underset' => [vsprintf('<munder>%2$s%1$s</munder>', [$this->argument($token), $this->argument($token)]), 'normal'],
            'text', 'textrm', 'mbox', 'textnormal' => ['<mtext>'.$this->escape($this->text($token)).'</mtext>', 'normal'],
            'textbf' => ['<mtext>'.$this->escape(Alphabets::apply($this->text($token), 'bold')).'</mtext>', 'normal'],
            'textit' => ['<mtext>'.$this->escape(Alphabets::apply($this->text($token), 'italic')).'</mtext>', 'normal'],
            'operatorname' => ['<mi>'.$this->escape(trim($this->text($token))).'</mi>', 'function'],
            'left' => [$this->fence($token), 'normal'],
            'begin' => [$this->environment($token), 'normal'],
            'not' => [$this->negated($token), 'normal'],
            'bmod' => ['<mo>mod</mo>', 'normal'],
            'pmod', 'mod' => [\sprintf('<mrow><mspace width="1em"></mspace>%s<mi>mod</mi><mspace width="0.3333em"></mspace>%s%s</mrow>', 'pmod' === $token->value ? '<mo>(</mo>' : '', $this->argument($token), 'pmod' === $token->value ? '<mo>)</mo>' : ''), 'normal'],
            '\\' => throw InvalidLatex::at($token->offset, 'a line break "\\\\" is only allowed in an environment'),
            'right' => throw InvalidLatex::at($token->offset, '"\right" has no "\left"'),
            'end' => throw InvalidLatex::at($token->offset, '"\end" has no "\begin"'),
            'middle' => throw InvalidLatex::at($token->offset, '"\middle" is only allowed between "\left" and "\right"'),
            default => $this->extended($token),
        };
    }

    /**
     * Colours, links, boxes, equation numbers and chemistry.
     *
     * @return array{string, string}
     */
    private function extended(Token $token): array
    {
        return match ($token->value) {
            'textcolor' => [\sprintf('<mstyle mathcolor="%s">%s</mstyle>', $this->color($token), $this->argument($token)), 'normal'],
            'boxed' => [\sprintf('<mrow style="border:%s;padding:0.2em">%s</mrow>', self::LINE, $this->argument($token)), 'normal'],
            'href', 'url' => [$this->link($token), 'normal'],
            'tag' => [$this->tagged($token), 'normal'],
            'notag', 'nonumber' => ['<mrow></mrow>', 'normal'],
            'ce' => [$this->chemistry($token), 'normal'],
            'color' => throw InvalidLatex::at($token->offset, '"\color" sets the colour of the rest of a group; the colour of an argument is written "\textcolor{colour}{…}"'),
            'hline' => throw InvalidLatex::at($token->offset, '"\hline" is only allowed at the start of a row of an environment'),
            default => throw InvalidLatex::at($token->offset, \sprintf('"\%s" is not supported', $token->value)),
        };
    }

    /**
     * The colour after `\color` or `\textcolor`, as `#rrggbb`: a name, `#rgb`, `#rrggbb`, or a value in the model
     * between brackets before it, `[HTML]` or `[rgb]`.
     */
    private function color(Token $command): string
    {
        $model = null;

        $open = $this->peekSolid();

        if ($open->is(Token::CHAR, '[')) {
            ++$this->position;
            $model = '';

            while (!$this->peek()->is(Token::CHAR, ']')) {
                if ($this->peek()->is(Token::END)) {
                    throw InvalidLatex::at($open->offset, 'this colour model is never closed by "]"');
                }

                $model .= $this->tokens[$this->position++]->raw;
            }

            ++$this->position;
        }

        $color = $this->text($command);

        return Colors::hex($color, null === $model ? null : trim($model)) ?? throw InvalidLatex::at($command->offset, \sprintf('the colour "%s" is not a name LaTeX knows, "#rrggbb", "[HTML]{rrggbb}" nor "[rgb]{r,g,b}"', mb_substr(trim(($model ?? '').' '.$color), 0, 40)));
    }

    /**
     * `\url{address}`, or `\href{address}{text}`: an HTML link in a text, its address an `http`, `https` or `mailto`
     * one, normalised.
     */
    private function link(Token $command): string
    {
        $address = $this->text($command);
        $href = Link::href($address) ?? throw InvalidLatex::at($command->offset, \sprintf('"%s" is not an http, https or mailto address', mb_substr(trim($address), 0, 80)));
        $label = 'href' === $command->value ? $this->text($command) : trim($address);

        return \sprintf('<mtext><a xmlns="http://www.w3.org/1999/xhtml" href="%s">%s</a></mtext>', $this->escape($href), $this->escape($label));
    }

    /**
     * `\tag{n}`, written `(n)`, or `\tag*{n}`, written as it is: the number of the formula, or of the row of an
     * environment. It takes no place where it is written.
     */
    private function tagged(Token $command): string
    {
        $starred = $this->peek()->is(Token::CHAR, '*');
        $this->position += $starred ? 1 : 0;
        $label = trim($this->text($command));

        if (null !== $this->tag) {
            throw InvalidLatex::at($command->offset, 'a formula, or a row of an environment, has one "\tag"');
        }

        $this->tag = '<mtext>'.$this->escape($starred ? $label : '('.$label.')').'</mtext>';

        return '<mrow></mrow>';
    }

    private function chemistry(Token $command): string
    {
        $offsets = [];
        $text = $this->text($command, $offsets, true);

        return new Chemistry()->convert($text, $offsets, $command->offset);
    }

    /**
     * The argument of a command or a script: a group, or one token — one digit of a number, the rest left for what
     * follows.
     */
    private function argument(Token $command): string
    {
        $token = $this->peekSolid();

        if ($token->is(Token::NUMBER) && \strlen($token->value) > 1) {
            $this->tokens[$this->position] = new Token(Token::NUMBER, substr($token->value, 1), $token->offset + 1, substr($token->raw, 1));

            return $this->number($token->value[0]);
        }

        if ($token->is(Token::END) || $token->is(Token::CLOSE) || $token->is(Token::ALIGN)) {
            throw InvalidLatex::at($token->offset, \sprintf('"%s" needs an argument', $command->raw));
        }

        if ($token->is(Token::OPEN)) {
            ++$this->position;

            return $this->inside($token, self::IN_GROUP, static fn (array $nodes): string => self::row($nodes));
        }

        return $this->deeper($token, fn (): string => $this->atom()[0]);
    }

    private function group(Token $open): string
    {
        return $this->inside($open, self::IN_GROUP, static fn (array $nodes): string => '<mrow>'.implode('', $nodes).'</mrow>');
    }

    /**
     * The expression after an opening brace, closed by its brace, one level deeper.
     *
     * @param Closure(list<string>): string $wrap
     */
    private function inside(Token $open, string $context, Closure $wrap): string
    {
        $nodes = $this->deeper($open, fn (): array => $this->expression($context));
        $close = $this->next();

        if (!$close->is(self::IN_GROUP === $context ? Token::CLOSE : Token::CHAR, self::IN_GROUP === $context ? Token::CLOSE : ']')) {
            throw InvalidLatex::at($open->offset, 'this group is never closed');
        }

        return $wrap($nodes);
    }

    /**
     * @template T
     *
     * @param Closure(): T $read
     *
     * @return T
     */
    private function deeper(Token $at, Closure $read): mixed
    {
        if (++$this->depth > $this->maxDepth) {
            throw InvalidLatex::at($at->offset, \sprintf('the formula nests deeper than %d levels', $this->maxDepth));
        }

        $result = $read();
        --$this->depth;

        return $result;
    }

    private function root(Token $command): string
    {
        $token = $this->peekSolid();

        if (!$token->is(Token::CHAR, '[')) {
            return '<msqrt>'.$this->argument($command).'</msqrt>';
        }

        ++$this->position;
        $index = $this->inside($token, self::IN_BRACKET, static fn (array $nodes): string => self::row($nodes));

        return \sprintf('<mroot>%s%s</mroot>', $this->argument($command), $index);
    }

    /**
     * The raw text of a group, spaces kept, its escaped characters unescaped; a single character without braces. The
     * braces inside it are kept when asked, and `$offsets` receives the byte of the formula each byte comes from.
     *
     * @param list<int> $offsets
     */
    private function text(Token $command, array &$offsets = [], bool $braces = false): string
    {
        $open = $this->peekSolid();

        if (!$open->is(Token::OPEN)) {
            $token = $this->next();

            if (!$token->is(Token::LETTER) && !$token->is(Token::NUMBER) && !$token->is(Token::CHAR)) {
                throw InvalidLatex::at($token->offset, \sprintf('"%s" needs a group', $command->raw));
            }

            array_push($offsets, ...array_map(static fn (int $byte): int => $token->offset + $byte, range(0, \strlen($token->raw) - 1)));

            return $token->raw;
        }

        ++$this->position;
        $text = '';
        $level = 0;

        while (true) {
            $token = $this->tokens[$this->position];

            if ($token->is(Token::END)) {
                throw InvalidLatex::at($open->offset, 'this group is never closed');
            }

            ++$this->position;

            if ($token->is(Token::CLOSE) && 0 === $level) {
                return $text;
            }

            $level += match ($token->type) {
                Token::OPEN => 1,
                Token::CLOSE => -1,
                default => 0,
            };
            $piece = match (true) {
                $token->is(Token::OPEN), $token->is(Token::CLOSE) => $braces ? $token->raw : '',
                $token->is(Token::COMMAND, ' ') => ' ',
                $token->is(Token::COMMAND) => 1 === \strlen($token->value) && isset(Symbols::OPERATORS[$token->value])
                    ? $token->value
                    : throw InvalidLatex::at($token->offset, \sprintf('"%s" is not allowed in a text', $token->raw)),
                default => $token->raw,
            };
            $text .= $piece;

            for ($byte = 0, $length = \strlen($piece); $byte < $length; ++$byte) {
                $offsets[] = $token->offset + min($byte, \strlen($token->raw) - 1);
            }
        }
    }

    private function font(Token $command, string $alphabet): string
    {
        $outer = $this->font;
        $this->font = $alphabet;
        $content = $this->argument($command);
        $this->font = $outer;

        return $content;
    }

    /**
     * `\left` … `\right`, each delimiter stretched over the content, `\middle` ones between.
     */
    private function fence(Token $left): string
    {
        $open = $this->delimiter($left);
        $nodes = $this->deeper($left, function (): array {
            $nodes = [];

            while (true) {
                array_push($nodes, ...$this->expression(self::IN_FENCE));

                if (!$this->peekSolid()->is(Token::COMMAND, 'middle')) {
                    return $nodes;
                }

                $middle = $this->next();
                $nodes[] = $this->stretchy($this->delimiter($middle));
            }
        });

        $right = $this->next();

        if (!$right->is(Token::COMMAND, 'right')) {
            throw InvalidLatex::at($left->offset, '"\left" has no "\right"');
        }

        return '<mrow>'.$this->stretchy($open).implode('', $nodes).$this->stretchy($this->delimiter($right)).'</mrow>';
    }

    private function sized(Token $command): string
    {
        $size = self::SIZES[$command->value] ?? self::SIZES[substr($command->value, 0, -1)];
        $delimiter = $this->delimiter($command);

        return '' === $delimiter ? '<mrow></mrow>' : \sprintf('<mo minsize="%1$sem" maxsize="%1$sem">%2$s</mo>', $size, $this->escape($delimiter));
    }

    /**
     * The delimiter after `\left`, `\middle`, `\right` or `\big`: empty for `.`.
     */
    private function delimiter(Token $command): string
    {
        $token = $this->peekSolid();
        $key = $token->is(Token::COMMAND) ? '\\'.$token->value : $token->raw;

        if (!isset(Symbols::DELIMITERS[$key])) {
            throw InvalidLatex::at($token->offset, \sprintf('"%s" needs a delimiter', $command->raw));
        }

        ++$this->position;

        return Symbols::DELIMITERS[$key];
    }

    /**
     * An environment of rows and columns: the matrices, `cases`, `aligned` and `array`, with the rules of `array`'s
     * specification and of `\hline`, and the number a row's `\tag` gives it in a last column.
     */
    private function environment(Token $begin): string
    {
        $name = trim($this->text($begin));

        if (!isset(Symbols::MATRICES[$name])) {
            throw InvalidLatex::at($begin->offset, \sprintf('the environment "%s" is not supported', $name));
        }

        [$aligns, $rules] = 'array' === $name ? $this->columns($begin, $this->text($begin)) : [[], []];
        [$rows, $below] = $this->deeper($begin, fn (): array => $this->rows($begin));
        $end = trim($this->text($begin));

        if ($end !== $name) {
            throw InvalidLatex::at($begin->offset, \sprintf('"\begin{%s}" is closed by "\end{%s}"', $name, $end));
        }

        $align = match ($name) {
            'cases' => ' columnalign="left"',
            'aligned', 'align', 'align*' => ' columnalign="right left right left"',
            'array' => [] === $aligns ? '' : ' columnalign="'.implode(' ', $aligns).'"',
            default => '',
        };
        $width = max(array_map(static fn (array $row): int => \count($row[0]), $rows));
        $tagged = [] !== array_filter($rows, static fn (array $row): bool => null !== $row[1]);
        $table = '';

        $last = \count($rows) - 1;

        foreach ($rows as $index => [$cells, $tag, $above]) {
            $table .= '<mtr>';

            foreach ($cells as $column => $cell) {
                $table .= '<mtd'.$this->rules($above, $below && $index === $last, 0 === $column && isset($rules[-1]), $rules[$column] ?? false).'>'.$cell.'</mtd>';
            }

            $table .= $tagged ? str_repeat('<mtd></mtd>', $width - \count($cells)).'<mtd>'.$tag.'</mtd></mtr>' : '</mtr>';
        }

        $table = \sprintf('<mtable%s>%s</mtable>', $align, $table);
        [$open, $close] = Symbols::MATRICES[$name];

        return '' === $open && '' === $close ? $table : '<mrow>'.$this->stretchy($open).$table.$this->stretchy($close).'</mrow>';
    }

    /**
     * The rows of an environment up to its `\end`, each with its cells, its number, and whether a rule is drawn above
     * it; and whether one is drawn below the last. A last empty row, after a final `\\`, is left out; a `\hline`
     * before it rules the last row below.
     *
     * @return array{non-empty-list<array{list<string>, ?string, bool}>, bool}
     */
    private function rows(Token $begin): array
    {
        $outer = $this->takeTag();
        $rows = [];
        $row = [];
        $cells = 0;
        $above = $this->hlines();

        while (true) {
            $row[] = self::row($this->expression(self::IN_CELL));

            if (++$cells > self::MAX_CELLS) {
                throw InvalidLatex::at($begin->offset, \sprintf('the environment holds more than %d cells', self::MAX_CELLS));
            }

            $token = $this->next();

            if ($token->is(Token::ALIGN)) {
                continue;
            }

            if ($token->is(Token::END)) {
                throw InvalidLatex::at($begin->offset, 'this environment is never ended');
            }

            $ended = $token->is(Token::COMMAND, 'end');

            $tag = $this->takeTag();
            $kept = !$ended || ['<mrow></mrow>'] !== $row || [] === $rows || null !== $tag;

            if ($kept) {
                $rows[] = [$row, $tag, $above];
            }

            if ($ended) {
                $this->tag = $outer;

                return [$rows, !$kept && $above];
            }

            $row = [];

            // The optional space after a line break, \\[2pt], is read and left out.
            if ($this->peekSolid()->is(Token::CHAR, '[')) {
                while (!$this->next()->is(Token::CHAR, ']') && !$this->peek()->is(Token::END)) {
                }
            }

            $above = $this->hlines();
        }
    }

    /**
     * The number `\tag` gave the formula or the row just read, if it gave one, forgotten for the next.
     */
    private function takeTag(): ?string
    {
        $tag = $this->tag;
        $this->tag = null;

        return $tag;
    }

    /**
     * Whether a row starts with `\hline`, those read.
     */
    private function hlines(): bool
    {
        $ruled = false;

        while ($this->peekSolid()->is(Token::COMMAND, 'hline')) {
            ++$this->position;
            $ruled = true;
        }

        return $ruled;
    }

    /**
     * The alignment of the columns of an `array`, from its specification — `l`, `c` and `r`, spaces ignored — and
     * whether a rule `|` is drawn after each column, the key -1 for the one before the first.
     *
     * @return array{list<string>, array<int, true>}
     */
    private function columns(Token $begin, string $specification): array
    {
        $aligns = [];
        $rules = [];

        foreach (str_split((string) preg_replace('/\s+/', '', $specification)) as $character) {
            if ('|' === $character) {
                $rules[\count($aligns) - 1] = true;

                continue;
            }

            $aligns[] = ['l' => 'left', 'c' => 'center', 'r' => 'right'][$character]
                ?? throw InvalidLatex::at($begin->offset, 'the columns of "array" are written with l, c, r and |');
        }

        return [$aligns, $rules];
    }

    /**
     * The `style` that draws the rules around a cell, empty without any.
     */
    private function rules(bool $above, bool $below, bool $left, bool $right): string
    {
        $borders = array_keys(array_filter(['top' => $above, 'bottom' => $below, 'left' => $left, 'right' => $right]));

        return [] === $borders ? '' : ' style="'.implode(';', array_map(static fn (string $side): string => 'border-'.$side.':'.self::LINE, $borders)).'"';
    }

    private function negated(Token $not): string
    {
        $token = $this->peekSolid();

        if ($token->is(Token::END)) {
            throw InvalidLatex::at($not->offset, '"\not" needs an operator');
        }

        $atom = $this->atom()[0];

        return str_starts_with($atom, '<mo') ? substr($atom, 0, -5)."\u{338}</mo>" : '<mrow>'.$atom.'<mo>'."\u{338}".'</mo></mrow>';
    }

    private function number(string $digits): string
    {
        return '<mn>'.Alphabets::apply($digits, $this->font).'</mn>';
    }

    private function identifier(string $letter): string
    {
        return 'normal' === $this->font ? '<mi mathvariant="normal">'.$letter.'</mi>' : '<mi>'.Alphabets::apply($letter, $this->font).'</mi>';
    }

    private function character(Token $token): string
    {
        return match ($token->value) {
            '\\' => throw InvalidLatex::at($token->offset, 'a backslash must be followed by a command'),
            '#', '$' => throw InvalidLatex::at($token->offset, \sprintf('"%s" must be written "\%1$s"', $token->value)),
            '-' => '<mo>−</mo>',
            '*' => '<mo>∗</mo>',
            "'" => '<mo>′</mo>',
            default => '<mo>'.$this->escape($token->value).'</mo>',
        };
    }

    /**
     * The next token, spaces included.
     */
    private function peek(): Token
    {
        return $this->tokens[$this->position];
    }

    /**
     * The next token that is not a space, the spaces before it read.
     */
    private function peekSolid(): Token
    {
        while ($this->tokens[$this->position]->is(Token::SPACE)) {
            ++$this->position;
        }

        return $this->tokens[$this->position];
    }

    private function next(): Token
    {
        $token = $this->peekSolid();

        if (!$token->is(Token::END)) {
            ++$this->position;
        }

        return $token;
    }

    /**
     * A script, refused when the atom already has one of that kind.
     */
    private function once(?string $existing, string $script, Token $token): string
    {
        if (null !== $existing) {
            throw InvalidLatex::at($token->offset, 'an atom has two superscripts or two subscripts; group them with braces');
        }

        return $script;
    }

    private function stretchy(string $delimiter): string
    {
        return '' === $delimiter ? '' : '<mo fence="true" stretchy="true">'.$this->escape($delimiter).'</mo>';
    }

    /**
     * @param list<string> $nodes
     */
    private static function row(array $nodes): string
    {
        return 1 === \count($nodes) ? $nodes[0] : '<mrow>'.implode('', $nodes).'</mrow>';
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_XML1, 'UTF-8');
    }
}
