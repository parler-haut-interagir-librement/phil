<?php

declare(strict_types=1);

namespace Phil\RelatedContent;

use DateTimeImmutable;
use Phil\RelatedContent\Exception\InvalidItem;

/**
 * What the library knows of a content or a product to find its neighbours: its identifier, its keywords by index — the
 * terms of each vocabulary, its authors, its series —, its date and its language. Nothing about its readers.
 *
 * Keywords are compared trimmed and in lowercase, each once; `broader` holds, by index, the broader terms of the
 * taxonomy that `TaxonomyExpander` adds, compared at a lower weight.
 *
 * ```php
 * new Item('article:42', ['topics' => ['Board games', 'Fantasy'], 'authors' => ['ada']], new DateTimeImmutable('2026-11-02'), 'en');
 * ```
 */
final readonly class Item
{
    public const int MAX_ID_LENGTH = 255;

    public const int MAX_INDICES = 32;

    public const int MAX_KEYWORDS = 200;

    /**
     * @var array<string, list<string>>
     */
    public array $keywords;

    /**
     * @var array<string, list<string>>
     */
    public array $broader;

    /**
     * @param array<string, list<string>> $keywords by index name
     * @param array<string, list<string>> $broader  the broader terms of the keywords, by index name
     *
     * @throws InvalidItem when the identifier is empty, too long or holds control characters, or when there are too
     *                     many indices or keywords
     */
    public function __construct(
        public string $id,
        array $keywords = [],
        public ?DateTimeImmutable $date = null,
        public ?string $locale = null,
        array $broader = [],
    ) {
        self::assertIdentifier($id);
        $this->keywords = $this->normalize($keywords);
        $this->broader = $this->normalize($broader);
    }

    /**
     * @throws InvalidItem when the identifier is empty, too long or holds control characters
     */
    public static function assertIdentifier(string $id): void
    {
        if ('' === $id || \strlen($id) > self::MAX_ID_LENGTH || 1 === preg_match('/[\x00-\x1F\x7F]/', $id)) {
            throw InvalidItem::identifier($id);
        }
    }

    /**
     * The same item with the broader terms of one index replaced.
     *
     * @param list<string> $terms
     */
    public function withBroader(string $index, array $terms): self
    {
        $broader = $this->broader;
        $broader[$index] = $terms;

        return new self($this->id, $this->keywords, $this->date, $this->locale, $broader);
    }

    /**
     * The keywords of an index, as the keys of a set.
     *
     * @return array<string, true>
     */
    public function keywordSet(string $index): array
    {
        return array_fill_keys($this->keywords[$index] ?? [], true);
    }

    /**
     * The keywords of an index and their broader terms, as the keys of a set.
     *
     * @return array<string, true>
     */
    public function expandedSet(string $index): array
    {
        return array_fill_keys([...$this->keywords[$index] ?? [], ...$this->broader[$index] ?? []], true);
    }

    /**
     * Whether a candidate may sit beside this item: same language, or either without one.
     */
    public function sharesLocaleWith(self $other): bool
    {
        return null === $this->locale || null === $other->locale || $this->locale === $other->locale;
    }

    /**
     * @param array<string, list<string>> $keywords
     *
     * @return array<string, list<string>>
     */
    private function normalize(array $keywords): array
    {
        if (\count($keywords) > self::MAX_INDICES) {
            throw InvalidItem::tooMany('indices', \count($keywords), self::MAX_INDICES);
        }

        $normalized = [];

        foreach ($keywords as $index => $values) {
            if (\count($values) > self::MAX_KEYWORDS) {
                throw InvalidItem::tooMany('keywords in '.$index, \count($values), self::MAX_KEYWORDS);
            }

            $set = [];

            foreach ($values as $value) {
                $value = mb_strtolower(trim($value));

                if ('' !== $value) {
                    $set[] = $value;
                }
            }

            $normalized[$index] = array_values(array_unique($set));
        }

        return $normalized;
    }
}
