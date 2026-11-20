<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * The traversal of the tree: the children an attempt offers, the flow forward and backward, and the checks of a
 * choice.
 *
 * @internal built by `SequencingRun` for each request
 */
final readonly class SequencingFlow
{
    public function __construct(
        private ActivityTree $tree,
        private SequencingData $data,
        private SequencingRules $rules,
    ) {
    }

    /**
     * The children of the activity an attempt offers: all of them in order, or those drawn and reordered by its
     * randomization controls, drawn once and kept in the state.
     *
     * @return list<Activity>
     */
    public function available(Activity $activity): array
    {
        $order = $this->data->record($activity->identifier)['order'];
        $controls = $activity->sequencing->randomizationControls;

        if (null === $order) {
            if (!$controls->isActive()) {
                return $activity->children;
            }

            $order = $this->draw($activity, $controls);
            $this->data->records[$activity->identifier] = ['order' => $order] + $this->data->record($activity->identifier);
        }

        return array_map($this->tree->get(...), $order);
    }

    /**
     * @return list<string>
     */
    private function draw(Activity $activity, RandomizationControls $controls): array
    {
        $identifiers = array_map(static fn (Activity $child): string => $child->identifier, $activity->children);
        $randomizer = new Randomizer(new Xoshiro256StarStar(hash('sha256', \sprintf('%d|%s|%d', $this->data->seed, $activity->identifier, $this->data->record($activity->identifier)['attempts']), true)));
        $count = $controls->selectCount;

        if (RandomizationTiming::Never !== $controls->selectionTiming && null !== $count && $count < \count($identifiers)) {
            // The keys come in the order of the children.
            $keys = $count < 1 || [] === $identifiers ? [] : $randomizer->pickArrayKeys($identifiers, $count);
            $identifiers = array_map(static fn (int $key): string => $identifiers[$key], $keys);
        }

        if (RandomizationTiming::Never !== $controls->randomizationTiming && $controls->reorderChildren) {
            $identifiers = $randomizer->shuffleArray($identifiers);
        }

        return $identifiers;
    }

    /**
     * From the candidate, the first leaf the flow may deliver in that direction: skipped activities passed over,
     * clusters entered; false when the flow goes past the end of the tree, null when it cannot go on.
     */
    public function flow(Activity $candidate, bool $forward, bool $checkFirst): Activity|false|null
    {
        $first = true;

        for ($steps = 2 * \count($this->tree->activities()) + 2; $steps > 0; --$steps) {
            $traversed = $checkFirst || !$first;
            $first = false;
            $parent = $this->tree->parent($candidate->identifier);

            if ($traversed && $parent instanceof Activity && !$parent->sequencing->controlMode->flow) {
                return null;
            }

            $skip = $traversed && $this->rules->isSkipped($candidate);

            if ($skip) {
                $next = $this->next($candidate, $forward);

                if (!$next instanceof Activity) {
                    return $next;
                }

                $candidate = $next;

                continue;
            }

            if (null !== $this->rules->checkActivity($candidate)) {
                return null;
            }

            if ($candidate->isLeaf()) {
                return $candidate;
            }

            $children = $this->available($candidate);
            $child = $forward || $candidate->sequencing->controlMode->forwardOnly ? ($children[0] ?? null) : ($children[\count($children) - 1] ?? null);

            if (null === $child) {
                $next = $this->next($candidate, $forward);

                if (!$next instanceof Activity) {
                    return $next;
                }

                $child = $next;
            }

            $candidate = $child;
        }

        return null;
    }

    /**
     * The sibling that follows or precedes the activity, going up the tree past the last or first child; false past
     * the end of the tree, null before its beginning or where the parent moves forward only.
     */
    public function next(Activity $activity, bool $forward): Activity|false|null
    {
        $parent = $this->tree->parent($activity->identifier);

        if (!$parent instanceof Activity) {
            return $forward ? false : null;
        }

        if (!$forward && $parent->sequencing->controlMode->forwardOnly) {
            return null;
        }

        $siblings = $this->available($parent);
        $index = array_find_key($siblings, static fn (Activity $sibling): bool => $sibling->identifier === $activity->identifier);
        $sibling = null === $index ? null : ($siblings[$forward ? $index + 1 : $index - 1] ?? null);

        return $sibling ?? $this->next($parent, $forward);
    }

    /**
     * Why a choice of that activity is refused — choice not allowed, the activity not offered or hidden, an
     * activity under way that may not be left, a backward move under forward only, a stop of forward traversal —, or
     * null.
     */
    public function choiceRefusal(string $target): ?string
    {
        foreach ($this->tree->path($target) as $activity) {
            $parent = $this->tree->parent($activity->identifier);

            if ($parent instanceof Activity && !$parent->sequencing->controlMode->choice) {
                return \sprintf('The children of "%s" are not chosen.', $parent->identifier);
            }

            if ($parent instanceof Activity && !\in_array($activity, $this->available($parent), true)) {
                return \sprintf('The activity "%s" is not offered in this attempt.', $activity->identifier);
            }

            if ($this->rules->ruleAction($activity, RuleTiming::Pre, [RuleAction::HiddenFromChoice]) instanceof RuleAction) {
                return \sprintf('The activity "%s" is hidden from choice.', $activity->identifier);
            }
        }

        $current = $this->data->current;

        if (null === $current) {
            return null;
        }

        $common = $this->commonAncestor($current, $target);

        foreach (array_reverse($this->tree->path($current)) as $activity) {
            if ($activity->identifier === $common->identifier) {
                break;
            }

            if ($this->data->record($activity->identifier)['active'] && !$activity->sequencing->controlMode->choiceExit) {
                return \sprintf('The activity "%s" may not be left by a choice.', $activity->identifier);
            }
        }

        $from = $this->branch($common, $current);
        $to = $this->branch($common, $target);

        if (!$from instanceof Activity || !$to instanceof Activity) {
            return null;
        }

        $siblings = $this->available($common);
        $start = (int) array_search($from, $siblings, true);
        $end = (int) array_search($to, $siblings, true);

        if ($end < $start && $common->sequencing->controlMode->forwardOnly) {
            return \sprintf('The children of "%s" are taken forward only.', $common->identifier);
        }

        for ($index = $start; $index < $end; ++$index) {
            if ($this->rules->ruleAction($siblings[$index], RuleTiming::Pre, [RuleAction::StopForwardTraversal]) instanceof RuleAction) {
                return \sprintf('The activity "%s" stops forward traversal.', $siblings[$index]->identifier);
            }
        }

        return null;
    }

    public function commonAncestor(string $first, string $second): Activity
    {
        $path = $this->tree->path($second);
        $common = $this->tree->root;

        foreach ($this->tree->path($first) as $index => $activity) {
            if (($path[$index] ?? null) !== $activity) {
                break;
            }

            $common = $activity;
        }

        return $common;
    }

    /**
     * The child of the ancestor on the path to the activity; null when the activity is the ancestor.
     */
    private function branch(Activity $ancestor, string $identifier): ?Activity
    {
        $path = $this->tree->path($identifier);
        $index = array_search($ancestor, $path, true);

        return false === $index ? null : ($path[$index + 1] ?? null);
    }
}
