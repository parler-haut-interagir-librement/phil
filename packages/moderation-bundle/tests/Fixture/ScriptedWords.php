<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Fixture;

use Override;
use Phil\ModerationBundle\Application\Contract\WordScreen;
use Phil\ModerationBundle\Application\Screening;
use Phil\ModerationBundle\Application\ScreeningVerdict;

/**
 * Watched words as a table: a word of the text gives its verdict, and is replaced by stars.
 */
final readonly class ScriptedWords implements WordScreen
{
    /**
     * @param array<string, ScreeningVerdict> $words
     */
    public function __construct(
        private array $words,
    ) {
    }

    #[Override]
    public function screen(string $text): Screening
    {
        $verdict = ScreeningVerdict::Allow;
        $matches = [];

        foreach ($this->words as $word => $wordVerdict) {
            if (str_contains($text, $word)) {
                $matches[] = $word;
                $text = str_replace($word, str_repeat('*', \strlen($word)), $text);
                $verdict = $wordVerdict;
            }
        }

        return new Screening($verdict, $text, $matches);
    }
}
