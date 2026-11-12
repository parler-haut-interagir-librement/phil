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
 * `video-captions`: a video carries a track of captions or subtitles, for readers who do not hear it. In HTML, a
 * `video` has a `track` child of kind `captions` or `subtitles`, the kind a track without one takes; in UJO, a `video`
 * block has such a track among its `tracks`. Whether the track says everything the sound says is the author's to check.
 */
final class VideoCaptions extends FragmentRule
{
    private const array CAPTION_KINDS = ['captions', 'subtitles'];

    #[Override]
    public function id(): string
    {
        return 'video-captions';
    }

    #[Override]
    public function criterion(): SuccessCriterion
    {
        return SuccessCriterion::CaptionsPrerecorded;
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
        foreach ($target->elements('video') as $video) {
            $source = $video->getAttribute('src');
            $captioned = false;

            for ($child = $video->firstElementChild; null !== $child; $child = $child->nextElementSibling) {
                $name = $child->localName;
                $source ??= 'source' === $name ? $child->getAttribute('src') : null;
                $captioned = $captioned || ('track' === $name && $this->isCaptions($child->getAttribute('kind')));
            }

            if (!$captioned && !$target->isHidden($video)) {
                yield new Violation($target->locate($video), new Message('video-captions.missing', ['source' => $source ?? '']));
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

        if ('video' !== $block->type) {
            return;
        }

        $tracks = UjoTarget::field($block, 'tracks');

        foreach (\is_array($tracks) ? $tracks : [] as $track) {
            $kind = \is_array($track) ? $track['kind'] ?? 'subtitles' : null;

            if (\is_string($kind) && $this->isCaptions($kind)) {
                return;
            }
        }

        $source = UjoTarget::field($block, 'sources', 0, 'url');

        yield new Violation($visit->locate('tracks'), new Message('video-captions.missing', ['source' => \is_string($source) ? $source : '']));
    }

    private function isCaptions(?string $kind): bool
    {
        return \in_array(strtolower(trim($kind ?? 'subtitles')), self::CAPTION_KINDS, true);
    }
}
