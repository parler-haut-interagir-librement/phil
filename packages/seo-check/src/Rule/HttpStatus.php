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
 * Every rendering answers with a success: search engines drop a page that answers with an error. The page at its own
 * address should not redirect, since its canonical address is where readers land; another path may.
 */
final readonly class HttpStatus implements SeoRule
{
    public const string NAME = 'http-status';

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(CheckedPage $page): iterable
    {
        foreach ($page->renderings as $index => $rendering) {
            $status = $rendering->page->status;
            $path = Excerpt::path($rendering->page->url);

            if ($status >= 400) {
                yield new Finding(self::NAME, Severity::Error, new Message('http-status.unsuccessful', ['status' => $status, 'path' => $path]), $path);
            } elseif ($status >= 300 && 0 === $index) {
                yield new Finding(self::NAME, Severity::Warning, new Message('http-status.redirect', ['status' => $status, 'path' => $path]), $path);
            }
        }
    }
}
