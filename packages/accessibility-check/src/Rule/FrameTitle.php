<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Rule;

use Override;
use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Message;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityCheck\Ujo\BlockVisit;
use Phil\AccessibilityCheck\Ujo\UjoTarget;
use Phil\AccessibilityCheck\Violation;

/**
 * `frame-title`: a frame says what it holds — a `title`, or a name of ARIA —, so that a reader can decide whether to
 * enter it. In HTML, every `iframe` and `frame` shown; in UJO, every `embed` block, whose `title` names the frame.
 */
final class FrameTitle extends FragmentRule
{
    #[Override]
    public function id(): string
    {
        return 'frame-title';
    }

    #[Override]
    public function criterion(): SuccessCriterion
    {
        return SuccessCriterion::NameRoleValue;
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
        foreach ($target->elements('iframe', 'frame') as $frame) {
            $named = '' !== $target->labelOf($frame) || '' !== HtmlTarget::collapse($frame->getAttribute('title') ?? '');

            if (!$named && !$target->isHidden($frame)) {
                yield new Violation($target->locate($frame), new Message('frame-title.missing', ['source' => $frame->getAttribute('src') ?? '']));
            }
        }
    }

    /**
     * @return iterable<Violation>
     */
    #[Override]
    protected function checkBlock(BlockVisit $visit, UjoTarget $target): iterable
    {
        $block = $visit->block;

        if ('embed' === $block->type && '' === UjoTarget::text(UjoTarget::field($block, 'title'))) {
            $source = UjoTarget::field($block, 'source');

            yield new Violation($visit->locate('title'), new Message('frame-title.missing', ['source' => \is_string($source) ? $source : '']));
        }
    }
}
