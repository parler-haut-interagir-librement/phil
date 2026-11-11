<?php

declare(strict_types=1);

namespace Phil\Passwords\Strength;

/**
 * The keys of a keyboard and their neighbours, for the estimator to recognise patterns typed by walking on it.
 *
 * A layout is written as rows of keys separated by spaces, each key its character and, on a typewriter keyboard, its
 * shifted character. On a typewriter keyboard the rows are staggered: each row is shifted right by its offset, in half
 * keys, and a key has six neighbours — left, right, and two above and two below. On a keypad the keys are aligned and a
 * key has eight neighbours. A character written on two keys belongs to the last. The bundled layouts are QWERTY
 * (US), AZERTY (French), QWERTZ (German) and the numeric keypad.
 */
final readonly class KeyboardLayout
{
    /** The neighbours on a staggered keyboard, in half keys: left, upper left, upper right, right, lower right, lower left. */
    private const array STAGGERED = [[-2, 0], [-1, -1], [1, -1], [2, 0], [1, 1], [-1, 1]];

    /** The neighbours on a keypad, in keys, clockwise from the left. */
    private const array ALIGNED = [[-1, 0], [-1, -1], [0, -1], [1, -1], [1, 0], [1, 1], [0, 1], [-1, 1]];

    /**
     * @param array<string, array{int, int}> $positions  each character to its key's position and whether it is shifted
     * @param array<int, list<?int>>         $neighbours each key to its neighbour in each direction, or null
     */
    private function __construct(
        public string $name,
        private array $positions,
        private array $neighbours,
        public int $keys,
        public float $averageDegree,
    ) {
    }

    /**
     * @param list<string> $rows    each a row of keys separated by spaces, each key its character then its shifted one
     * @param list<int>    $offsets each row's shift to the right, in half keys
     */
    public static function staggered(string $name, array $rows, array $offsets): self
    {
        return self::build($name, $rows, static fn (int $row, int $column): int => 2 * $column + ($offsets[$row] ?? 0), self::STAGGERED);
    }

    /**
     * @param list<string> $rows each a row of keys separated by spaces; a key written `_` is a gap
     */
    public static function aligned(string $name, array $rows): self
    {
        return self::build($name, $rows, static fn (int $row, int $column): int => $column, self::ALIGNED);
    }

    public static function qwerty(): self
    {
        return self::staggered('qwerty', [
            '`~ 1! 2@ 3# 4$ 5% 6^ 7& 8* 9( 0) -_ =+',
            'qQ wW eE rR tT yY uU iI oO pP [{ ]} \|',
            'aA sS dD fF gG hH jJ kK lL ;: \'"',
            'zZ xX cC vV bB nN mM ,< .> /?',
        ], [0, 3, 4, 5]);
    }

    public static function azerty(): self
    {
        return self::staggered('azerty', [
            '²³ &1 é2 "3 \'4 (5 -6 è7 _8 ç9 à0 )° =+',
            'aA zZ eE rR tT yY uU iI oO pP ^¨ $£',
            'qQ sS dD fF gG hH jJ kK lL mM ù% *µ',
            '<> wW xX cC vV bB nN ,? ;. :/ !§',
        ], [0, 3, 4, 3]);
    }

    public static function qwertz(): self
    {
        return self::staggered('qwertz', [
            '^° 1! 2" 3§ 4$ 5% 6& 7/ 8( 9) 0= ß? ´`',
            'qQ wW eE rR tT zZ uU iI oO pP üÜ +*',
            "aA sS dD fF gG hH jJ kK lL öÖ äÄ #'",
            '<> yY xX cC vV bB nN mM ,; .: -_',
        ], [0, 3, 4, 3]);
    }

    public static function keypad(): self
    {
        return self::aligned('keypad', [
            '_ / * -',
            '7 8 9 +',
            '4 5 6',
            '1 2 3',
            '_ 0 .',
        ]);
    }

    /**
     * QWERTY, AZERTY, QWERTZ and the keypad.
     *
     * @return list<self>
     */
    public static function bundled(): array
    {
        return [self::qwerty(), self::azerty(), self::qwertz(), self::keypad()];
    }

    /**
     * The direction from the key of one character to the key of the next, and whether the next is shifted; null when
     * they are not neighbours.
     *
     * @return ?array{int, bool}
     */
    public function step(string $from, string $to): ?array
    {
        if (!isset($this->positions[$from], $this->positions[$to])) {
            return null;
        }

        $target = $this->positions[$to][0];

        foreach ($this->neighbours[$this->positions[$from][0]] as $direction => $key) {
            if ($key === $target) {
                return [$direction, 1 === $this->positions[$to][1]];
            }
        }

        return null;
    }

    public function isShifted(string $character): bool
    {
        return isset($this->positions[$character]) && 1 === $this->positions[$character][1];
    }

    /**
     * @param list<string>            $rows
     * @param callable(int, int): int $column
     * @param list<array{int, int}>   $directions
     */
    private static function build(string $name, array $rows, callable $column, array $directions): self
    {
        $grid = [];
        $positions = [];
        $key = 0;

        foreach ($rows as $row => $line) {
            foreach (explode(' ', $line) as $index => $characters) {
                if ('_' === $characters) {
                    continue;
                }

                $grid[$row][$column($row, $index)] = $key;

                foreach (mb_str_split($characters) as $shifted => $character) {
                    $positions[$character] = [$key, $shifted];
                }

                ++$key;
            }
        }

        $neighbours = [];
        $degrees = 0;

        foreach ($grid as $row => $keys) {
            foreach ($keys as $x => $at) {
                foreach ($directions as [$dx, $dy]) {
                    $neighbour = $grid[$row + $dy][$x + $dx] ?? null;
                    $neighbours[$at][] = $neighbour;
                    $degrees += null === $neighbour ? 0 : 1;
                }
            }
        }

        return new self($name, $positions, $neighbours, $key, 0 === $key ? 0.0 : $degrees / $key);
    }
}
