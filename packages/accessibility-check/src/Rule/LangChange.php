<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Rule;

use Override;
use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Language\LanguageTag;
use Phil\AccessibilityCheck\Location;
use Phil\AccessibilityCheck\Message;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityCheck\Ujo\BlockVisit;
use Phil\AccessibilityCheck\Ujo\UjoTarget;
use Phil\AccessibilityCheck\Violation;

/**
 * `lang-change`: a passage in another language says which, with a well-formed tag. In HTML, every `lang` below the
 * `html` element is a well-formed tag; `lang=""`, a passage of unknown language, is noted. In UJO, the `language`
 * tune of a block names a well-formed language, and a change of direction — a tune with a direction only, a `bdi`, a
 * `bdo` or a `span` with `dir` and without `lang` — is noted, since a passage written the other way is most often in
 * another language.
 */
final class LangChange extends FragmentRule
{
    #[Override]
    public function id(): string
    {
        return 'lang-change';
    }

    #[Override]
    public function criterion(): SuccessCriterion
    {
        return SuccessCriterion::LanguageOfParts;
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
        foreach ($target->elements() as $element) {
            $name = $element->localName;
            $lang = $element->getAttribute('lang');

            if ('html' === $name) {
                continue;
            }

            if (null !== $lang) {
                yield from $this->checkTag($lang, $target->locate($element));
            } elseif ($target->isUjoField() && ('bdi' === $name || 'bdo' === $name || ('span' === $name && $element->hasAttribute('dir')))) {
                yield new Violation($target->locate($element), new Message('lang-change.direction-only'), Severity::Notice);
            }
        }
    }

    /**
     * @return iterable<Violation>
     */
    #[Override]
    protected function checkBlock(BlockVisit $visit, UjoTarget $target): iterable
    {
        $tune = $visit->block->tune('language');
        $tune = \is_array($tune) ? $tune : [];

        $language = $tune['language'] ?? null;

        if (\is_string($language)) {
            yield from $this->checkTag($language, $visit->locate());
        } elseif (isset($tune['direction'])) {
            yield new Violation($visit->locate(), new Message('lang-change.direction-only'), Severity::Notice);
        }
    }

    /**
     * @return iterable<Violation>
     */
    private function checkTag(string $lang, Location $location): iterable
    {
        $lang = trim($lang);

        if ('' === $lang) {
            yield new Violation($location, new Message('lang-change.empty'), Severity::Notice);
        } elseif (!LanguageTag::isWellFormed($lang)) {
            yield new Violation($location, new Message('lang-change.invalid', ['lang' => $lang]));
        }
    }
}
