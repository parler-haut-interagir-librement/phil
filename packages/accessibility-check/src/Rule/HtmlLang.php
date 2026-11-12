<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Rule;

use Dom\Element;
use Override;
use Phil\AccessibilityCheck\Contract\HtmlRule;
use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Language\LanguageTag;
use Phil\AccessibilityCheck\Message;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityCheck\Violation;

/**
 * `html-lang`: a whole page says its language, a well-formed tag in the `lang` of its `html` element, so that a
 * screen reader reads it with the right voice. A fragment of HTML takes the language of the page it is shown in and
 * is not checked.
 */
final class HtmlLang implements HtmlRule
{
    #[Override]
    public function id(): string
    {
        return 'html-lang';
    }

    #[Override]
    public function criterion(): SuccessCriterion
    {
        return SuccessCriterion::LanguageOfPage;
    }

    #[Override]
    public function severity(): Severity
    {
        return Severity::Error;
    }

    /**
     * @return iterable<Violation>
     */
    #[Override]
    public function checkHtml(HtmlTarget $target): iterable
    {
        $html = $target->document->documentElement;

        if (!$target->isFullDocument || !$html instanceof Element) {
            return;
        }

        $lang = trim($html->getAttribute('lang') ?? '');

        if ('' === $lang) {
            yield new Violation($target->locate($html), new Message('html-lang.missing'));
        } elseif (!LanguageTag::isWellFormed($lang)) {
            yield new Violation($target->locate($html), new Message('html-lang.invalid', ['lang' => $lang]));
        }
    }
}
