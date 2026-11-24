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
 * The text of a link says where it leads: search engines read it as a description of the page it links to, and a
 * reader skimming the links of a page reads only them. « Click here », « en savoir plus » say nothing.
 *
 * The texts compared with are those of the page's primary language, from a list an application replaces or completes;
 * a page of a language without a list passes.
 */
final readonly class LinkText implements SeoRule
{
    public const string NAME = 'link-text';

    /** @var array<string, array<string, string>> each generic text normalized, by primary language, to the text as given */
    private array $generic;

    /**
     * @param array<string, list<string>> $generic the generic texts by primary language, in any case
     */
    public function __construct(array $generic = GenericLinkTexts::DEFAULTS)
    {
        $lists = [];

        foreach ($generic as $language => $texts) {
            foreach ($texts as $text) {
                $lists[strtolower($language)][GenericLinkTexts::normalize($text)] = $text;
            }
        }

        $this->generic = $lists;
    }

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(CheckedPage $page): iterable
    {
        $generic = $this->generic[LanguageTag::primary($page->locale)] ?? [];

        foreach ($page->primary()->markup->links as $link) {
            if (null !== $link->href && isset($generic[GenericLinkTexts::normalize($link->text)])) {
                yield new Finding(self::NAME, Severity::Warning, new Message('link-text.generic', ['text' => Excerpt::of($link->text), 'href' => Excerpt::of($link->href)]), Excerpt::of($link->href));
            }
        }
    }
}
