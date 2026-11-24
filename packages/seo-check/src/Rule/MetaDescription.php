<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Rule;

use Override;
use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\Contract\SeoRule;
use Phil\SeoCheck\Finding;
use Phil\SeoCheck\Markup\MarkupReader;
use Phil\SeoCheck\Message;
use Phil\SeoCheck\Severity;

/**
 * The page has one `<meta name="description">`, not empty: results pages show it under the title, and write one of
 * their own, from whatever text they find, when it is missing. Its length is information only: a results page cuts a
 * long one, and a short one says little.
 */
final readonly class MetaDescription implements SeoRule
{
    public const string NAME = 'meta-description';

    /** Below this many characters, a description says little. */
    public const int MIN_LENGTH = 50;

    /** Beyond this many characters, results pages cut a description. */
    public const int MAX_LENGTH = 160;

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
        $descriptions = $page->primary()->markup->meta('description');

        if ([] === $descriptions) {
            yield new Finding(self::NAME, Severity::Warning, new Message('meta-description.missing'));

            return;
        }

        if (\count($descriptions) > 1) {
            yield new Finding(self::NAME, Severity::Warning, new Message('meta-description.multiple', ['count' => \count($descriptions)]));
        }

        $description = MarkupReader::text($descriptions[0]);
        $length = mb_strlen($description);

        if (0 === $length) {
            yield new Finding(self::NAME, Severity::Warning, new Message('meta-description.empty'));
        } elseif ($length < $this->minLength) {
            yield new Finding(self::NAME, Severity::Info, new Message('meta-description.short', ['length' => $length, 'min' => $this->minLength]), $description);
        } elseif ($length > $this->maxLength) {
            yield new Finding(self::NAME, Severity::Info, new Message('meta-description.long', ['length' => $length, 'max' => $this->maxLength]), Excerpt::of($description));
        }
    }
}
