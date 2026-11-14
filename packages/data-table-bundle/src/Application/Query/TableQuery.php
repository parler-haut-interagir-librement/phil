<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Query;

/**
 * What a table asks of its data source. Every field in it comes from the table's declaration, never from a request.
 */
final readonly class TableQuery
{
    /**
     * @param list<TextCriterion|ChoiceCriterion|DateRangeCriterion|BooleanCriterion> $filters all of them hold
     * @param list<Sort>                                                              $sorts   in their order
     */
    public function __construct(
        public array $filters,
        public array $sorts,
        public int $page,
        public int $pageSize,
    ) {
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->pageSize;
    }

    /**
     * The same query on another page.
     */
    public function onPage(int $page): self
    {
        return clone ($this, ['page' => $page]);
    }
}
