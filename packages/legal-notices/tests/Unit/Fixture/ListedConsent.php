<?php

declare(strict_types=1);

namespace Phil\LegalNotices\Tests\Unit\Fixture;

use Override;
use Phil\LegalNotices\ConsentCategory;
use Phil\LegalNotices\Contract\ConsentCategories;

final readonly class ListedConsent implements ConsentCategories
{
    /**
     * @param list<ConsentCategory> $categories
     */
    public function __construct(
        private array $categories,
    ) {
    }

    #[Override]
    public function categories(): array
    {
        return $this->categories;
    }
}
