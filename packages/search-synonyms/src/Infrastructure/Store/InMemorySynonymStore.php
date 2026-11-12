<?php

declare(strict_types=1);

namespace Phil\SearchSynonyms\Infrastructure\Store;

use Override;
use Phil\SearchSynonyms\Application\Contract\SynonymStore;
use Phil\SearchSynonyms\Domain\ValueObject\SynonymList;

/**
 * Synonyms kept in memory, for tests and for lists the application builds itself.
 */
final class InMemorySynonymStore implements SynonymStore
{
    /**
     * @var array<string, SynonymList>
     */
    private array $lists = [];

    public function __construct(SynonymList ...$lists)
    {
        foreach ($lists as $list) {
            $this->save($list);
        }
    }

    #[Override]
    public function get(string $language): SynonymList
    {
        $language = SynonymList::language($language);

        return $this->lists[$language] ?? SynonymList::of($language);
    }

    #[Override]
    public function save(SynonymList $list): void
    {
        $this->lists[$list->language] = $list;
    }

    #[Override]
    public function languages(): array
    {
        $languages = array_keys($this->lists);
        sort($languages);

        return $languages;
    }
}
