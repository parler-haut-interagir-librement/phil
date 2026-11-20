<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing\Prerequisite;

use Closure;
use Phil\ElearningPackages\Exception\PackageRefused;

/**
 * The prerequisites of an item of SCORM 1.2, `<adlcp:prerequisites type="aicc_script">`: an expression over the
 * identifiers of other items, joined by `&` (and) and `|` (or), negated by `~`, grouped by parentheses, a status
 * compared by `=` or `<>` — `passed`, `completed`, `failed`, `incomplete`, `browsed`, `not attempted`, or their
 * initials —, and sets, `2*{a,b,c}`, met when at least that many of their members are. An item named alone is met
 * once passed or completed. An item the tree does not hold reads as not attempted.
 */
final readonly class Prerequisites
{
    public const int MAX_LENGTH = 4096;

    public const int MAX_DEPTH = 32;

    public const array STATUSES = ['passed', 'completed', 'failed', 'incomplete', 'browsed', 'not attempted'];

    private function __construct(
        public string $expression,
        private Node $root,
    ) {
    }

    /**
     * @throws PackageRefused when the expression is empty, longer than 4096 bytes, nested deeper than 32 levels, or is
     *                        not one the grammar allows
     */
    public static function parse(string $expression): self
    {
        if (\strlen($expression) > self::MAX_LENGTH) {
            throw PackageRefused::because(\sprintf('A prerequisites expression is longer than %d bytes.', self::MAX_LENGTH));
        }

        $tokens = self::tokens($expression);
        $position = 0;
        $root = self::disjunction($tokens, $position, 0);

        if (isset($tokens[$position])) {
            throw self::refused($expression, \sprintf('"%s" is unexpected', $tokens[$position]));
        }

        return new self($expression, $root);
    }

    /**
     * Whether the prerequisites are met, the status of each item given by its identifier.
     *
     * @param Closure(string): string $status the `lesson_status` of the item of that identifier
     */
    public function isMet(Closure $status): bool
    {
        return $this->root->isMet($status);
    }

    /**
     * @return list<string> the identifiers the expression names, each once, in order
     */
    public function identifiers(): array
    {
        return array_values(array_unique($this->root->identifiers()));
    }

    /**
     * @return list<string>
     */
    private static function tokens(string $expression): array
    {
        if (false === preg_match_all('/\s*(?:("[^"]*")|(<>|[()&|~,*{}=])|([\w.\-:]+)|(\S))/u', $expression, $matches, \PREG_SET_ORDER)) {
            throw self::refused($expression, 'it is not UTF-8');
        }

        $tokens = [];

        foreach ($matches as $match) {
            if (isset($match[4])) {
                throw self::refused($expression, \sprintf('"%s" is not a token', $match[4]));
            }

            $tokens[] = trim($match[0]);
        }

        return $tokens;
    }

    /**
     * @param list<string> $tokens
     */
    private static function disjunction(array $tokens, int &$position, int $depth): Node
    {
        $nodes = [self::conjunction($tokens, $position, $depth)];

        while ('|' === ($tokens[$position] ?? null)) {
            ++$position;
            $nodes[] = self::conjunction($tokens, $position, $depth);
        }

        return 1 === \count($nodes) ? $nodes[0] : new AtLeast(1, $nodes);
    }

    /**
     * @param list<string> $tokens
     */
    private static function conjunction(array $tokens, int &$position, int $depth): Node
    {
        $nodes = [self::unary($tokens, $position, $depth)];

        while ('&' === ($tokens[$position] ?? null)) {
            ++$position;
            $nodes[] = self::unary($tokens, $position, $depth);
        }

        return 1 === \count($nodes) ? $nodes[0] : new AtLeast(\count($nodes), $nodes);
    }

    /**
     * @param list<string> $tokens
     */
    private static function unary(array $tokens, int &$position, int $depth): Node
    {
        if ($depth >= self::MAX_DEPTH) {
            throw self::refused(implode(' ', $tokens), \sprintf('it nests deeper than %d levels', self::MAX_DEPTH));
        }

        $token = $tokens[$position] ?? throw self::refused(implode(' ', $tokens), 'it ends too soon');
        ++$position;

        if ('~' === $token) {
            return new Negation(self::unary($tokens, $position, $depth + 1));
        }

        if ('(' === $token) {
            $node = self::disjunction($tokens, $position, $depth + 1);
            self::expect($tokens, $position, ')');

            return $node;
        }

        if (1 === preg_match('/^\d+$/D', $token) && '*' === ($tokens[$position] ?? null)) {
            ++$position;
            self::expect($tokens, $position, '{');
            $nodes = [self::disjunction($tokens, $position, $depth + 1)];

            while (',' === ($tokens[$position] ?? null)) {
                ++$position;
                $nodes[] = self::disjunction($tokens, $position, $depth + 1);
            }

            self::expect($tokens, $position, '}');

            return new AtLeast((int) $token, $nodes);
        }

        if (1 !== preg_match('/^[\w.\-:]+$/uD', $token)) {
            throw self::refused(implode(' ', $tokens), \sprintf('"%s" is not an item', $token));
        }

        $operator = $tokens[$position] ?? null;

        if ('=' !== $operator && '<>' !== $operator) {
            return new ItemStatus($token, ['passed', 'completed']);
        }

        ++$position;

        return new ItemStatus($token, [self::status($tokens, $position)], '<>' === $operator);
    }

    /**
     * @param list<string> $tokens
     */
    private static function status(array $tokens, int &$position): string
    {
        $token = $tokens[$position] ?? throw self::refused(implode(' ', $tokens), 'a status is missing');
        ++$position;
        $word = strtolower(trim($token, '"'));

        if ('not' === $word && 'attempted' === strtolower($tokens[$position] ?? '')) {
            ++$position;
            $word = 'not attempted';
        }

        $status = match ($word) {
            'p' => 'passed',
            'c' => 'completed',
            'f' => 'failed',
            'i' => 'incomplete',
            'b' => 'browsed',
            'n' => 'not attempted',
            default => $word,
        };

        if (!\in_array($status, self::STATUSES, true)) {
            throw self::refused(implode(' ', $tokens), \sprintf('"%s" is not a status', mb_substr($token, 0, 40)));
        }

        return $status;
    }

    /**
     * @param list<string> $tokens
     */
    private static function expect(array $tokens, int &$position, string $token): void
    {
        if ($token !== ($tokens[$position] ?? null)) {
            throw self::refused(implode(' ', $tokens), \sprintf('"%s" is expected', $token));
        }

        ++$position;
    }

    private static function refused(string $expression, string $why): PackageRefused
    {
        return PackageRefused::because(\sprintf('The prerequisites "%s" cannot be read: %s.', mb_substr(self::shown($expression), 0, 120), self::shown($why)));
    }

    private static function shown(string $text): string
    {
        return (string) preg_replace('/[\x00-\x1F\x7F]/', '?', mb_scrub($text, 'UTF-8'));
    }
}
