<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Bridge\SocialCards;

use Override;
use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\Contract\SeoRule;
use Phil\SeoCheck\Finding;
use Phil\SeoCheck\Message;
use Phil\SeoCheck\Severity;
use Phil\SocialCards\Preview\Severity as TagSeverity;
use Phil\SocialCards\Preview\TagCheck;
use Phil\SocialCards\Preview\TagReader;

/**
 * The share tags of the page are complete, by `TagCheck` of phil/social-cards: the tags Open Graph requires, absolute
 * addresses, a description, an image with its text and its size, a card of X, and `og:url` agreeing with the canonical
 * link. An error of the check is an error, a warning a warning.
 *
 * The message of each finding is the check's, in English, given as the `{message}` placeholder; the tag it is about is
 * the `{tag}` placeholder.
 */
final readonly class ShareTagsRule implements SeoRule
{
    public const string NAME = 'share-tags';

    public function __construct(
        private TagReader $reader = new TagReader(),
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
        $primary = $page->primary()->page;

        foreach (TagCheck::check($this->reader->read($primary->html, $primary->url->toString())) as $finding) {
            yield new Finding(
                self::NAME,
                TagSeverity::Error === $finding->severity ? Severity::Error : Severity::Warning,
                new Message('share-tags.issue', ['tag' => $finding->tag, 'message' => $finding->message]),
                $finding->tag,
            );
        }
    }
}
