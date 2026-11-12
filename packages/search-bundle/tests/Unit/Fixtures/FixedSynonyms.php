<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Unit\Fixtures;

use Override;
use Phil\SearchBundle\Application\Contract\SynonymProvider;
use Phil\SearchBundle\Domain\ValueObject\SynonymRule;

/**
 * Synonyms given per language, and how many times each language was asked.
 */
final class FixedSynonyms implements SynonymProvider
{
    /**
     * @var array<string, int>
     */
    public array $asked = [];

    /**
     * @param array<string, list<SynonymRule>> $rules
     */
    public function __construct(
        private readonly array $rules,
    ) {
    }

    #[Override]
    public function rules(string $language): iterable
    {
        $this->asked[$language] = ($this->asked[$language] ?? 0) + 1;

        return $this->rules[$language] ?? [];
    }
}
