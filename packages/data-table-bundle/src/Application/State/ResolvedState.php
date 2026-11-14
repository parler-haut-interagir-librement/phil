<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\State;

use Phil\DataTableBundle\Application\Query\BooleanCriterion;
use Phil\DataTableBundle\Application\Query\ChoiceCriterion;
use Phil\DataTableBundle\Application\Query\DateRangeCriterion;
use Phil\DataTableBundle\Application\Query\Sort;
use Phil\DataTableBundle\Application\Query\TextCriterion;

/**
 * A table's state once checked against its declaration.
 */
final readonly class ResolvedState
{
    /**
     * @param string                                                                  $sort     the sort asked for and known, as the address writes it
     * @param Sort|null                                                               $active   the first sort, by column name
     * @param list<Sort>                                                              $sorts    the sorts by fields, ending on the identifier
     * @param array<string, string>                                                   $filters  the values kept, by address key
     * @param list<TextCriterion|ChoiceCriterion|DateRangeCriterion|BooleanCriterion> $criteria
     * @param list<string>                                                            $columns  the visible columns, when some are hidden
     * @param list<string>                                                            $selected
     */
    public function __construct(
        public string $sort,
        public ?Sort $active,
        public array $sorts,
        public int $page,
        public int $pageSize,
        public array $filters,
        public array $criteria,
        public array $columns,
        public array $selected,
    ) {
    }
}
