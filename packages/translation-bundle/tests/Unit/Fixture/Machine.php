<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Tests\Unit\Fixture;

use Closure;
use Override;
use Phil\TranslationBundle\Application\Contract\MachineTranslator;
use Phil\TranslationMemory\LanguageTag;

/**
 * A machine translator of the test: it answers what its closure makes of the sources, and remembers each call.
 */
final class Machine implements MachineTranslator
{
    /**
     * @var list<array{list<string>, string, string}>
     */
    public array $calls = [];

    /**
     * @param Closure(list<string>): list<string> $answer
     */
    public function __construct(
        private readonly Closure $answer,
    ) {
    }

    /**
     * A translator that writes each source in upper case.
     */
    public static function shouting(): self
    {
        return new self(static fn (array $sources): array => array_map(mb_strtoupper(...), $sources));
    }

    #[Override]
    public function name(): string
    {
        return 'test-machine';
    }

    #[Override]
    public function translate(array $sources, LanguageTag $sourceLanguage, LanguageTag $targetLanguage): array
    {
        $this->calls[] = [$sources, $sourceLanguage->value, $targetLanguage->value];

        return ($this->answer)($sources);
    }
}
