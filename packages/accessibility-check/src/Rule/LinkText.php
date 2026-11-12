<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Rule;

use Override;
use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Language\LanguageTag;
use Phil\AccessibilityCheck\Location;
use Phil\AccessibilityCheck\Message;
use Phil\AccessibilityCheck\Policy;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityCheck\Ujo\BlockVisit;
use Phil\AccessibilityCheck\Ujo\UjoTarget;
use Phil\AccessibilityCheck\Violation;

/**
 * `link-text`: the name of a link says where it leads. "Click here", "ici", "lire la suite" say nothing to a reader who
 * lists the links of a page. The name is compared with the generic texts of its language — the `lang` of the link or
 * of its ancestors, else the language of the content —, or with those of every language when its language is not
 * known. In UJO, the text of a `button` block is compared too.
 */
final class LinkText extends FragmentRule
{
    #[Override]
    public function id(): string
    {
        return 'link-text';
    }

    #[Override]
    public function criterion(): SuccessCriterion
    {
        return SuccessCriterion::LinkPurposeInContext;
    }

    #[Override]
    public function severity(): Severity
    {
        return Severity::Warning;
    }

    /**
     * @return iterable<Violation>
     */
    #[Override]
    public function checkHtml(HtmlTarget $target): iterable
    {
        foreach ($target->elements('a') as $link) {
            $href = $link->getAttribute('href');

            if (null !== $href && !$target->isHidden($link)) {
                yield from $this->check($target->accessibleName($link), $href, $target->languageOf($link), $target->policy, $target->locate($link));
            }
        }
    }

    /**
     * @return iterable<Violation>
     */
    #[Override]
    protected function checkBlock(BlockVisit $visit, UjoTarget $target): iterable
    {
        if ('button' === $visit->block->type) {
            $link = UjoTarget::field($visit->block, 'link');
            $text = UjoTarget::field($visit->block, 'text');

            yield from $this->check(\is_string($text) ? $text : '', \is_string($link) ? $link : '', $visit->language, $target->policy, $visit->locate('text'));
        }
    }

    /**
     * @return iterable<Violation>
     */
    private function check(string $name, string $href, ?string $language, Policy $policy, Location $location): iterable
    {
        $text = GenericLinkText::normalize($name);
        $generic = null === $language
            ? array_merge(...array_values($policy->genericLinkTexts))
            : $policy->genericLinkTexts[LanguageTag::primary($language)] ?? [];

        if ('' !== $text && \in_array($text, $generic, true)) {
            yield new Violation($location, new Message('link-text.generic', ['href' => $href, 'text' => HtmlTarget::collapse($name)]));
        }
    }
}
