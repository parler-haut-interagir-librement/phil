<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Unit\Rule;

use Phil\SeoCheck\Contract\SeoRule;
use Phil\SeoCheck\Rule\DefaultRules;
use Phil\SeoCheck\Tests\Support\MemoryLinkStatuses;
use Phil\SeoCheck\Tests\Support\MemoryRenderedPages;
use Phil\SeoCheck\Tests\Support\Pages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefaultRules::class)]
final class DefaultRulesTest extends TestCase
{
    public function testTheRulesComeInTheOrderOfTheReport(): void
    {
        self::assertSame(
            ['http-status', 'page-size', 'document-title', 'meta-description', 'document-language', 'single-h1', 'canonical', 'indexing', 'hreflang', 'crawlable-links', 'link-text', 'image-alt'],
            array_map(static fn (SeoRule $rule): string => $rule->name(), DefaultRules::create()),
        );
    }

    public function testTheDeadLinksAreCheckedWhenTheirStatusesAreKnown(): void
    {
        $rules = DefaultRules::create(statuses: new MemoryLinkStatuses());

        self::assertCount(13, $rules);
        self::assertSame('broken-links', $rules[12]->name());
    }

    public function testTheTranslationsAreReadThroughTheRenderedPages(): void
    {
        $pages = new MemoryRenderedPages();
        $page = Pages::checked(Pages::html());

        foreach (DefaultRules::create($pages) as $rule) {
            iterator_to_array($rule->check($page), false);
        }

        self::assertSame(['https://example.org/en/news/opening'], $pages->asked);
    }
}
