<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Glossary\Contract;

use Phil\TranslationMemory\LanguageTag;

/**
 * The inflected forms of a phrase of a glossary in its language: plurals, genders, cases — whatever the words take in
 * a sentence. The glossary finds and accepts them as the phrase itself.
 */
interface Inflector
{
    /**
     * The other forms of a phrase, without the phrase itself; none for a language the inflector does not know.
     *
     * @return list<string>
     */
    public function forms(string $phrase, LanguageTag $language): array;
}
