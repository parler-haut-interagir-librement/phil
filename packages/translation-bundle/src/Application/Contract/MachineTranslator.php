<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Application\Contract;

use Phil\TranslationMemory\LanguageTag;

/**
 * A machine translation service, behind an adapter named after it. Its targets are proposed — never translated —, and
 * marked as machine output until a person writes another.
 */
interface MachineTranslator
{
    /**
     * The name of the service, shown with what it proposes.
     */
    public function name(): string;

    /**
     * The targets of sources of inline HTML, one per source, in the same order.
     *
     * @param non-empty-list<string> $sources
     *
     * @return list<string>
     */
    public function translate(array $sources, LanguageTag $sourceLanguage, LanguageTag $targetLanguage): array;
}
