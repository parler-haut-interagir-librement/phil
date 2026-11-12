<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Xliff;

use Phil\TranslationMemory\Exception\InvalidLanguage;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Segment;
use Phil\TranslationMemory\SegmentState;
use Phil\TranslationMemory\Text;

/**
 * Merges the targets of an imported XLIFF file into the segments of a document.
 *
 * A unit is applied to the segment of its key when the segment's source has the same words as the unit's — the file
 * was made from the current source —, and when it brings a target: as suggested, translated or reviewed, as the file
 * says. Tags the source does not hold are left out of the target, so a file cannot bring markup the document never
 * had. Every other unit is skipped, with its reason.
 */
final class XliffMerge
{
    private function __construct()
    {
    }

    /**
     * @param iterable<Segment> $segments
     *
     * @throws InvalidLanguage when a language is given and the file's is another
     */
    public static function merge(iterable $segments, XliffFile $file, ?LanguageTag $sourceLanguage = null, ?LanguageTag $targetLanguage = null): MergeResult
    {
        if ($sourceLanguage instanceof LanguageTag && !$file->sourceLanguage->equals($sourceLanguage)) {
            throw InvalidLanguage::mismatch('source', $sourceLanguage->value, $file->sourceLanguage->value);
        }

        if ($targetLanguage instanceof LanguageTag && !($file->targetLanguage?->equals($targetLanguage) ?? false)) {
            throw InvalidLanguage::mismatch('target', $targetLanguage->value, $file->targetLanguage->value ?? 'none');
        }

        $byKey = [];

        foreach ($segments as $segment) {
            $byKey[$segment->key] = $segment;
        }

        $applied = [];
        $skipped = [];

        foreach ($file->units as $unit) {
            $segment = $byKey[$unit->key] ?? null;
            $target = null === $segment || null === $unit->target ? '' : InlineCodes::restrictTo($unit->target, $segment->source);
            $reason = match (true) {
                !$segment instanceof Segment => SkipReason::UnknownKey,
                Text::plain($segment->source) !== Text::plain($unit->source) => SkipReason::SourceChanged,
                !Text::hasWords($target) => SkipReason::NoTarget,
                self::isUnchanged($segment, $unit, $target) => SkipReason::Unchanged,
                default => null,
            };

            if (null !== $reason) {
                $skipped[] = new Skipped($unit->key, $reason);

                continue;
            }

            $byKey[$unit->key] = match ($unit->state) {
                SegmentState::Translated => $segment->translate($target),
                SegmentState::Reviewed => $segment->translate($target)->review(),
                default => $segment->suggest($target),
            };
            $applied[] = $unit->key;
        }

        return new MergeResult(array_values($byKey), $applied, $skipped);
    }

    /**
     * Whether the unit brings the target the segment has, in the same state or only proposed.
     */
    private static function isUnchanged(Segment $segment, XliffUnit $unit, string $target): bool
    {
        return Text::normalize($target) === Text::normalize($segment->target ?? '') && (SegmentState::Suggested === $unit->state || $unit->state === $segment->state);
    }
}
