<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Rule;

use Phil\SeoCheck\Contract\LinkStatuses;
use Phil\SeoCheck\Contract\RenderedPages;
use Phil\SeoCheck\Contract\SeoRule;

/**
 * The rules of the check, after the SEO audits of Lighthouse, in the order of their reports: the response, the head of
 * the page, its body, its links. The reciprocity of `hreflang` is checked when `RenderedPages` is given, the dead links
 * when `LinkStatuses` is.
 *
 * The structured data and the share tags are checked by the rules of `Bridge\`, added when their libraries are
 * installed.
 */
final class DefaultRules
{
    private function __construct()
    {
    }

    /**
     * @return list<SeoRule>
     */
    public static function create(?RenderedPages $pages = null, ?LinkStatuses $statuses = null): array
    {
        $rules = [
            new HttpStatus(),
            new PageSize(),
            new DocumentTitle(),
            new MetaDescription(),
            new DocumentLanguage(),
            new SingleH1(),
            new Canonical(),
            new Indexing(),
            new Hreflang($pages),
            new CrawlableLinks(),
            new LinkText(),
            new ImageAlt(),
        ];

        if ($statuses instanceof LinkStatuses) {
            $rules[] = new BrokenLinks($statuses);
        }

        return $rules;
    }
}
