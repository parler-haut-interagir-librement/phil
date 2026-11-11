<?php

declare(strict_types=1);

namespace Phil\PrintPreflight\Check;

use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Edge;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\Length;
use Phil\PrintPreflight\Page;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Rectangle;
use Phil\PrintPreflight\Rule;

/**
 * Where each image and element sits against the trim box: content that crosses the trim edge is cut, content in the
 * safety margin risks it, an element meant to bleed must run past the trim edge by the printer's bleed wherever it
 * touches it, and an element entirely outside the trim box never shows.
 */
final readonly class Placement implements Check
{
    public function check(Document $document, PrintProfile $profile): array
    {
        $findings = [];

        foreach ($document->pages as $index => $page) {
            $number = $index + 1;

            foreach ($page->images as $image) {
                $finding = $this->finding($image->id, $image->frame, $image->bleeds, $page, $number, $profile);

                if ($finding instanceof Finding) {
                    $findings[] = $finding;
                }
            }

            foreach ($page->boxes as $box) {
                $finding = $this->finding($box->id, $box->frame, $box->bleeds, $page, $number, $profile);

                if ($finding instanceof Finding) {
                    $findings[] = $finding;
                }
            }
        }

        return $findings;
    }

    private function finding(string $id, Rectangle $frame, bool $bleeds, Page $page, int $number, PrintProfile $profile): ?Finding
    {
        $width = $page->trim->width->points;
        $height = $page->trim->height->points;
        $tolerance = Length::TOLERANCE;

        if ($frame->right() <= $tolerance || $frame->left() >= $width - $tolerance || $frame->bottom() <= $tolerance || $frame->top() >= $height - $tolerance) {
            return Finding::of(Rule::PlacementOutsideTrim, \sprintf('"%s" on page %d lies entirely outside the trim box and will not show.', $id, $number), $number, $id);
        }

        if ($bleeds) {
            $reach = $profile->bleed->points - $tolerance;
            $short = $this->edges(
                $frame->top() <= $tolerance && $frame->top() > -$reach,
                $frame->right() >= $width - $tolerance && $frame->right() < $width + $reach,
                $frame->bottom() >= $height - $tolerance && $frame->bottom() < $height + $reach,
                $frame->left() <= $tolerance && $frame->left() > -$reach,
            );

            return [] === $short ? null : Finding::of(Rule::PlacementShortOfBleed, \sprintf('"%s" on page %d stops short of the %s bleed at the %s edge; a white line may show after trimming.', $id, $number, $profile->bleed->describe(), Edge::describe($short)), $number, $id, ['edges' => Edge::describe($short), 'bleed' => $profile->bleed->describe()]);
        }

        $crossed = $this->edges(
            $frame->top() < -$tolerance,
            $frame->right() > $width + $tolerance,
            $frame->bottom() > $height + $tolerance,
            $frame->left() < -$tolerance,
        );

        if ([] !== $crossed) {
            return Finding::of(Rule::PlacementCrossesTrim, \sprintf('"%s" on page %d crosses the trim edge at the %s and will be cut.', $id, $number, Edge::describe($crossed)), $number, $id, ['edges' => Edge::describe($crossed)]);
        }

        $margin = $profile->safetyMargin->points - $tolerance;
        $close = $this->edges(
            $frame->top() < $margin,
            $frame->right() > $width - $margin,
            $frame->bottom() > $height - $margin,
            $frame->left() < $margin,
        );

        return [] === $close ? null : Finding::of(Rule::PlacementInSafetyMargin, \sprintf('"%s" on page %d is inside the %s safety margin at the %s edge.', $id, $number, $profile->safetyMargin->describe(), Edge::describe($close)), $number, $id, ['edges' => Edge::describe($close), 'margin' => $profile->safetyMargin->describe()]);
    }

    /**
     * @return list<Edge>
     */
    private function edges(bool $top, bool $right, bool $bottom, bool $left): array
    {
        $edges = [];

        if ($top) {
            $edges[] = Edge::Top;
        }

        if ($right) {
            $edges[] = Edge::Right;
        }

        if ($bottom) {
            $edges[] = Edge::Bottom;
        }

        if ($left) {
            $edges[] = Edge::Left;
        }

        return $edges;
    }
}
