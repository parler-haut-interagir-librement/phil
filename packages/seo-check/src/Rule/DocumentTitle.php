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
 * The page has one `<title>`, not empty, of a length a results page shows whole: it is the link readers click.
 */
final readonly class DocumentTitle implements SeoRule
{
    public const string NAME = 'document-title';

    /** Below this many characters, a title says too little to tell the page from others. */
    public const int MIN_LENGTH = 10;

    /** Beyond this many characters, results pages cut a title. */
    public const int MAX_LENGTH = 60;

    public function __construct(
        private int $minLength = self::MIN_LENGTH,
        private int $maxLength = self::MAX_LENGTH,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(CheckedPage $page): iterable
    {
        $titles = $page->primary()->markup->titles;

        if ([] === $titles) {
            yield new Finding(self::NAME, Severity::Error, new Message('document-title.missing'));

            return;
        }

        if (\count($titles) > 1) {
            yield new Finding(self::NAME, Severity::Warning, new Message('document-title.multiple', ['count' => \count($titles)]));
        }

        $length = mb_strlen($titles[0]);

        if (0 === $length) {
            yield new Finding(self::NAME, Severity::Error, new Message('document-title.empty'));
        } elseif ($length < $this->minLength) {
            yield new Finding(self::NAME, Severity::Warning, new Message('document-title.short', ['length' => $length, 'min' => $this->minLength]), $titles[0]);
        } elseif ($length > $this->maxLength) {
            yield new Finding(self::NAME, Severity::Warning, new Message('document-title.long', ['length' => $length, 'max' => $this->maxLength]), Excerpt::of($titles[0]));
        }
    }
}
