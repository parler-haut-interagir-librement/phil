<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Tests\Unit\Fixture;

use Override;
use Phil\TranslationBundle\Application\TranslationPolicy;
use Phil\TranslationMemory\LanguageTag;

/**
 * A policy set by the test: who may translate, and which documents not, who may review, and who may edit glossaries.
 */
final class Policy implements TranslationPolicy
{
    /**
     * @param list<string> $hidden the documents the user may not translate, whatever `$translate` says
     */
    public function __construct(
        public bool $translate = true,
        public bool $review = true,
        public bool $glossary = true,
        public array $hidden = [],
    ) {
    }

    #[Override]
    public function canTranslate(string $document, LanguageTag $targetLanguage): bool
    {
        return $this->translate && !\in_array($document, $this->hidden, true);
    }

    #[Override]
    public function canReview(string $document, LanguageTag $targetLanguage): bool
    {
        return $this->review;
    }

    #[Override]
    public function canEditGlossary(LanguageTag $sourceLanguage, LanguageTag $targetLanguage): bool
    {
        return $this->glossary;
    }
}
