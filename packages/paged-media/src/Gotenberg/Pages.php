<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg;

use Phil\PagedMedia\Gotenberg\Exception\InvalidStage;
use Stringable;

/**
 * A page selection: a page (`3`), a range (`2-5`), a list of both (`1,3,5-7`), or `all`, `odd`, `even` or `last` on
 * their own. Pages count from 1, from the PDF the stage receives.
 */
final readonly class Pages implements Stringable
{
    private const array WORDS = ['all', 'odd', 'even', 'last'];

    private function __construct(public string $value)
    {
    }

    public static function of(string $selection): self
    {
        $selection = str_replace(' ', '', $selection);

        if (\in_array($selection, self::WORDS, true)) {
            return new self($selection);
        }

        foreach (explode(',', $selection) as $part) {
            $bounds = explode('-', $part);

            if (\count($bounds) > 2 || [] !== array_filter($bounds, static fn (string $bound): bool => !ctype_digit($bound) || $bound < 1) || $bounds[0] > end($bounds)) {
                throw InvalidStage::because(\sprintf('A page selection is a page, a range or a list of both, counted from 1 and in ascending ranges, or one of all, odd, even and last; "%s" is not.', $selection));
            }
        }

        return new self($selection);
    }

    public static function all(): self
    {
        return new self('all');
    }

    public static function odd(): self
    {
        return new self('odd');
    }

    public static function even(): self
    {
        return new self('even');
    }

    public static function last(): self
    {
        return new self('last');
    }

    public static function page(int $page): self
    {
        return self::of((string) $page);
    }

    public static function range(int $first, int $last): self
    {
        return self::of($first.'-'.$last);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
