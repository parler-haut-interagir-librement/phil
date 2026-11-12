<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Check;

use Phil\Fonts\Font;
use Phil\TranslationMemory\Check\Contract\Check;
use Phil\TranslationMemory\Check\Contract\TextWidth;

/**
 * The quality checks of translated segments, run in turn on a target: each check the segment's flags do not dismiss
 * reports its issues, in the order of the checks.
 */
final readonly class Checker
{
    /**
     * @param list<Check> $checks
     */
    public function __construct(
        private array $checks,
    ) {
    }

    /**
     * The checks of the library: punctuation, spaces, tags, plurals and targets identical to their source; the size
     * in pixels when the template's text width is given, and the characters its font draws when the face is.
     */
    public static function defaults(?TextWidth $width = null, ?float $maximum = null, ?Font $font = null): self
    {
        $checks = [new Punctuation(), new Spaces(), new Tags(), new Plurals(), new SameAsSource()];

        if ($width instanceof TextWidth) {
            $checks[] = new MaxSize($width, $maximum);
        }

        if ($font instanceof Font) {
            $checks[] = new Glyphs($font);
        }

        return new self($checks);
    }

    /**
     * The names of the checks, in order.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(static fn (Check $check): string => $check->name(), $this->checks);
    }

    /**
     * The issues of a target, check by check; the checks its flags dismiss are not run.
     *
     * @return list<CheckIssue>
     */
    public function check(Checked $checked): array
    {
        $issues = [];

        foreach ($this->checks as $check) {
            if (!$checked->flags->dismisses($check->name())) {
                $issues = [...$issues, ...$check->check($checked)];
            }
        }

        return $issues;
    }
}
