<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Rule;

use Override;
use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\Contract\SeoRule;
use Phil\SeoCheck\Finding;
use Phil\SeoCheck\Message;
use Phil\SeoCheck\Severity;

/**
 * The page is no larger than the check reads: what lies beyond was not checked, and search engines stop reading a page
 * long before its end too.
 */
final readonly class PageSize implements SeoRule
{
    public const string NAME = 'page-size';

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(CheckedPage $page): iterable
    {
        $primary = $page->primary();

        if ($primary->markup->truncated) {
            yield new Finding(self::NAME, Severity::Warning, new Message('page-size.truncated', ['bytes' => \strlen($primary->page->html)]));
        }
    }
}
