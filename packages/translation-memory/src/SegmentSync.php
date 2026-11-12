<?php

declare(strict_types=1);

namespace Phil\TranslationMemory;

/**
 * Brings the stored segments of a document up to date with its source, cut again.
 *
 * Each source segment is matched, in this order, with:
 *
 * 1. the stored segment of the same key and the same fingerprint, kept as it is;
 * 2. a stored segment of the same fingerprint under another key — a sentence that moved because one was added before
 *    it, a block moved into a column —, carried to the new key, preferring one of the same field;
 * 3. the stored segment of the same key, whose source changed: stale when it has a target, untranslated otherwise;
 * 4. nothing: a new segment, untranslated.
 *
 * Stored segments matched by none are removed.
 */
final class SegmentSync
{
    private function __construct()
    {
    }

    /**
     * @param iterable<Segment>   $stored
     * @param list<SourceSegment> $source
     */
    public static function sync(iterable $stored, array $source): SyncResult
    {
        $byKey = [];

        foreach ($stored as $segment) {
            $byKey[$segment->key] = $segment;
        }

        $matched = [];
        $claimed = [];

        foreach ($source as $index => $piece) {
            $segment = $byKey[$piece->key] ?? null;

            if ($segment instanceof Segment && Fingerprint::of($piece->text)->equals($segment->fingerprint)) {
                $matched[$index] = $segment->withSource($piece->text);
                $claimed[$piece->key] = $index;
            }
        }

        $moved = [];

        foreach ($source as $index => $piece) {
            if (isset($matched[$index])) {
                continue;
            }

            $found = self::moved($byKey, $claimed, $piece);

            if ($found instanceof Segment) {
                $matched[$index] = $found->withKey($piece->key)->withSource($piece->text);
                $claimed[$found->key] = $index;
                $moved[$piece->key] = $found->key;
            }
        }

        $segments = [];
        $added = [];
        $stale = [];

        foreach ($source as $index => $piece) {
            $segment = $matched[$index] ?? null;

            if (!$segment instanceof Segment) {
                $earlier = isset($claimed[$piece->key]) ? null : $byKey[$piece->key] ?? null;

                if ($earlier instanceof Segment) {
                    $claimed[$piece->key] = $index;
                    $segment = $earlier->withSource($piece->text);
                    $stale = SegmentState::Stale === $segment->state ? [...$stale, $piece->key] : $stale;
                } else {
                    $segment = Segment::fromSource($piece);
                    $added[] = $piece->key;
                }
            }

            $segments[] = $segment;
        }

        $removed = array_values(array_filter($byKey, static fn (Segment $segment): bool => !isset($claimed[$segment->key])));

        return new SyncResult($segments, $added, $stale, $moved, $removed);
    }

    /**
     * A stored segment of the same fingerprint as the piece, not yet matched, with a target; one of the same field
     * first.
     *
     * @param array<string, Segment> $byKey
     * @param array<string, int>     $claimed the keys of the stored segments matched, to the index of the source piece
     */
    private static function moved(array $byKey, array $claimed, SourceSegment $piece): ?Segment
    {
        $fingerprint = Fingerprint::of($piece->text);
        $field = self::field($piece->key);
        $found = null;

        foreach ($byKey as $key => $segment) {
            if (isset($claimed[$key]) || null === $segment->target || !$segment->fingerprint->equals($fingerprint)) {
                continue;
            }

            if (self::field($key) === $field) {
                return $segment;
            }

            $found ??= $segment;
        }

        return $found;
    }

    private static function field(string $key): string
    {
        $hash = strrpos($key, '#');

        return false === $hash ? $key : substr($key, 0, $hash);
    }
}
