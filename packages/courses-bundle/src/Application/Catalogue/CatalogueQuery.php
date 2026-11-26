<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Catalogue;

use Phil\CoursesBundle\Domain\Catalogue\CatalogueSort;
use Phil\CoursesBundle\Domain\Catalogue\PriceFilter;
use Phil\CoursesBundle\Domain\Equivalence\LanguageTag;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Model\CatalogueEntry;

/**
 * What a learner asks of a catalogue: words its titles and descriptions hold, a tag, free or sold courses, an order and
 * a page; with the learner's language, a course of a group of equivalent courses is listed once, in that language. A
 * value that is not one of these reads as its default, so that an address typed by hand lists the catalogue all the
 * same.
 */
final readonly class CatalogueQuery
{
    public const int TEXT_LENGTH = 100;

    public const int MAX_PAGE = 1_000;

    /**
     * @param int<1, max> $page
     */
    private function __construct(
        public ?string $text,
        public ?string $tag,
        public PriceFilter $price,
        public CatalogueSort $sort,
        public ?string $language,
        public int $page,
    ) {
    }

    /**
     * Every course, in the order the catalogue placed them, the first page; listed once per group of equivalent
     * courses when a language is given.
     */
    public static function of(
        ?string $text = null,
        ?string $tag = null,
        PriceFilter $price = PriceFilter::Any,
        CatalogueSort $sort = CatalogueSort::Placed,
        ?string $language = null,
        int $page = 1,
    ): self {
        return new self(self::text($text), self::tag($tag), $price, $sort, self::language($language), max(1, min($page, self::MAX_PAGE)));
    }

    /**
     * Reads the query string of a catalogue page: `q`, `tag`, `price` (`free` or `paid`), `sort` (`title`, `newest` or
     * `price`) and `page`. What is missing or not one of these values reads as the default.
     *
     * @param array<array-key, mixed> $query
     */
    public static function fromQuery(array $query, ?string $language = null): self
    {
        $page = $query['page'] ?? null;

        return self::of(
            self::string($query['q'] ?? null),
            self::string($query['tag'] ?? null),
            PriceFilter::tryFrom(self::string($query['price'] ?? null) ?? '') ?? PriceFilter::Any,
            CatalogueSort::tryFrom(self::string($query['sort'] ?? null) ?? '') ?? CatalogueSort::Placed,
            $language,
            \is_string($page) && 1 === preg_match('/^[1-9]\d{0,3}$/D', $page) ? (int) $page : 1,
        );
    }

    /**
     * The same query on another page.
     */
    public function onPage(int $page): self
    {
        return new self($this->text, $this->tag, $this->price, $this->sort, $this->language, max(1, min($page, self::MAX_PAGE)));
    }

    /**
     * The values of the query string that ask for this query, the defaults left out; the language is the request's.
     *
     * @return array<string, string>
     */
    public function parameters(): array
    {
        return array_filter([
            'q' => $this->text,
            'tag' => $this->tag,
            'price' => PriceFilter::Any === $this->price ? null : $this->price->value,
            'sort' => CatalogueSort::Placed === $this->sort ? null : $this->sort->value,
            'page' => 1 === $this->page ? null : (string) $this->page,
        ], static fn (?string $value): bool => null !== $value);
    }

    private static function string(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }

    private static function text(?string $text): ?string
    {
        $text = trim(mb_substr(trim(mb_scrub((string) $text, 'UTF-8')), 0, self::TEXT_LENGTH));

        return '' === $text ? null : $text;
    }

    private static function tag(?string $tag): ?string
    {
        if (null === $tag) {
            return null;
        }

        try {
            return CatalogueEntry::tag(mb_scrub($tag, 'UTF-8'));
        } catch (InvalidCourse) {
            return null;
        }
    }

    private static function language(?string $language): ?string
    {
        if (null === $language) {
            return null;
        }

        try {
            return LanguageTag::normalize($language);
        } catch (InvalidCourse) {
            return null;
        }
    }
}
