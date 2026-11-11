<?php

declare(strict_types=1);

namespace Phil\Typography\Text;

use Closure;

/**
 * The text of one block, in parts: each text node of a paragraph, a heading or a list item is a part, so that a quote
 * opened before `<em>` and closed after it is still seen as one pair. A locked part — the content of a protected
 * element such as `<code>` — is seen by the fixers as context and never changed.
 */
final readonly class Run
{
    /**
     * @param list<string> $texts  the parts, in reading order
     * @param list<bool>   $locked for each part, whether it is locked; a missing entry is not
     */
    public function __construct(
        public array $texts,
        public array $locked = [],
    ) {
    }

    /**
     * A run of one part.
     */
    public static function of(string $text): self
    {
        return new self([$text]);
    }

    public function isLocked(int $part): bool
    {
        return $this->locked[$part] ?? false;
    }

    /**
     * Each part that is not locked, given to `$fix` with the character before it — the last one of the parts before,
     * already fixed — and the character after it, or `''` at the edges of the run.
     *
     * @param Closure(string, string, string): string $fix
     */
    public function map(Closure $fix): self
    {
        $texts = $this->texts;
        $after = $this->firstCharactersAfter();
        $before = '';

        foreach ($texts as $part => $text) {
            if (!$this->isLocked($part)) {
                $texts[$part] = $fix($text, $before, $after[$part]);
            }

            $before = '' === $texts[$part] ? $before : mb_substr($texts[$part], -1);
        }

        return new self($texts, $this->locked);
    }

    /**
     * Each part that is not locked, rewritten by `$fix` with the characters around it: `$fix` receives the part between
     * the character before and the character after, and its result is kept when it still begins and ends with them;
     * otherwise — when the fix changed one of them — the part is fixed alone.
     *
     * @param Closure(string): string $fix
     */
    public function replace(Closure $fix): self
    {
        return $this->map(static function (string $text, string $before, string $after) use ($fix): string {
            $fixed = $fix($before.$text.$after);
            $length = \strlen($fixed) - \strlen($before) - \strlen($after);

            if ($length >= 0 && str_starts_with($fixed, $before) && str_ends_with($fixed, $after)) {
                return substr($fixed, \strlen($before), $length);
            }

            return $fix($text);
        });
    }

    /**
     * Every character of the run, in one list across its parts, given to `$rewrite`, which returns what to write for
     * each: a fixer that pairs quotes sees a whole paragraph at once. What it returns for a locked part is ignored.
     *
     * @param Closure(list<string>): array<int, string> $rewrite
     */
    public function characters(Closure $rewrite): self
    {
        $characters = [];

        foreach ($this->texts as $text) {
            array_push($characters, ...mb_str_split($text));
        }

        $rewritten = $rewrite($characters);
        $texts = [];
        $index = 0;

        foreach ($this->texts as $part => $text) {
            $fixed = '';

            foreach (mb_str_split($text) as $character) {
                $fixed .= $this->isLocked($part) ? $character : $rewritten[$index] ?? $character;
                ++$index;
            }

            $texts[] = $fixed;
        }

        return new self($texts, $this->locked);
    }

    /**
     * @return list<string> for each part, the first character of the parts after it
     */
    private function firstCharactersAfter(): array
    {
        $reversed = [];
        $next = '';

        foreach (array_reverse($this->texts) as $text) {
            $reversed[] = $next;
            $next = '' === $text ? $next : mb_substr($text, 0, 1);
        }

        return array_reverse($reversed);
    }
}
