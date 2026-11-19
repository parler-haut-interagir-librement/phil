<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Application\Contract;

use Phil\LibraryBundle\Application\Search\SearchCriteria;
use Phil\LibraryBundle\Application\Search\SearchResult;

/**
 * The catalogue searched: one page of the titles that meet the criteria, in the order of their names.
 */
interface TitleSearch
{
    public function search(SearchCriteria $criteria, int $page, int $perPage): SearchResult;
}
