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
 * The page has one first-level heading, with text: it says what the page is about, to readers and to search engines.
 */
final readonly class SingleH1 implements SeoRule
{
    public const string NAME = 'single-h1';

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(CheckedPage $page): iterable
    {
        $headings = $page->primary()->markup->headings;

        if ([] === $headings) {
            yield new Finding(self::NAME, Severity::Warning, new Message('single-h1.missing'));
        }

        if (\count($headings) > 1) {
            yield new Finding(self::NAME, Severity::Warning, new Message('single-h1.multiple', ['count' => \count($headings)]));
        }

        if (\in_array('', $headings, true)) {
            yield new Finding(self::NAME, Severity::Warning, new Message('single-h1.empty'));
        }
    }
}
