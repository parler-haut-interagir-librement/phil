<?php

declare(strict_types=1);

namespace Phil\LegalNotices\Contract;

use Phil\LegalNotices\ConsentCategory;

/**
 * The categories of cookies a privacy policy lists, read from wherever the site's consent banner keeps them.
 */
interface ConsentCategories
{
    /**
     * @return list<ConsentCategory> in the order the policy lists them
     */
    public function categories(): array;
}
