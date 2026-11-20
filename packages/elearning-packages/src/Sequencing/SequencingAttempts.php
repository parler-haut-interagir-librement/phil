<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * The beginning and the end of the attempts on the activities: a new attempt clears what the activity — and, under
 * the control modes of a cluster, its descendants — knew of the last one; the end of an attempt completes and
 * satisfies a tracked leaf whose content did not say otherwise, then rolls up.
 *
 * @internal built by `SequencingRun` for each request
 */
final readonly class SequencingAttempts
{
    public function __construct(
        private ActivityTree $tree,
        private SequencingData $data,
        private SequencingRollup $rollup,
    ) {
    }

    /**
     * Ends the attempts under way from the activity up to the ancestor, both left out.
     */
    public function endActiveBetween(string $from, string $ancestor): void
    {
        foreach (array_reverse($this->tree->path($from)) as $activity) {
            if ($activity->identifier === $ancestor) {
                return;
            }

            if ($activity->identifier !== $from && $this->data->record($activity->identifier)['active']) {
                $this->endAttempt($activity->identifier);
            }
        }
    }

    public function endAttempt(string $identifier): void
    {
        $activity = $this->tree->get($identifier);
        $record = $this->data->record($identifier);
        $delivery = $activity->sequencing->deliveryControls;

        if ($activity->isLeaf()) {
            if ($delivery->tracked && !$record['suspended']) {
                if (!$delivery->completionSetByContent && null === $record['completed']) {
                    $record['completed'] = true;
                }

                $primary = $activity->sequencing->primaryObjective();

                if (!$delivery->objectiveSetByContent && null === ($record['objectives'][$primary->key()]['satisfied'] ?? null)) {
                    $this->data->records[$identifier] = $record;
                    $this->data->setSatisfied($activity, $primary, true);
                    $record = $this->data->record($identifier);
                }
            }
        } else {
            $record['suspended'] = array_find($activity->children, fn (Activity $child): bool => $this->data->record($child->identifier)['suspended']) instanceof Activity;
        }

        $record['active'] = false;
        $this->data->records[$identifier] = $record;
        $this->rollup->rollup($identifier);

        // The next attempt draws its children again; the rollup above still read those of this one.
        if (!$activity->isLeaf() && $activity->sequencing->randomizationControls->drawsOnEachAttempt()) {
            $this->data->records[$identifier]['order'] = null;
        }
    }

    public function newAttempt(Activity $activity): void
    {
        $record = $this->data->record($activity->identifier);
        $this->data->records[$activity->identifier] = [
            'attempts' => $record['attempts'] + 1,
            'active' => true,
            'completed' => null,
            'duration' => null,
            'objectives' => [],
        ] + $record;

        if ($activity->isLeaf() || 0 === $record['attempts']) {
            return;
        }

        $mode = $activity->sequencing->controlMode;
        $pending = $activity->children;

        while ([] !== $pending) {
            $child = array_shift($pending);
            $childRecord = $this->data->record($child->identifier);

            if ($mode->useCurrentAttemptObjectiveInfo) {
                $childRecord['objectives'] = [];
            }

            if ($mode->useCurrentAttemptProgressInfo) {
                $childRecord['completed'] = null;
            }

            $this->data->records[$child->identifier] = $childRecord;
            array_push($pending, ...$child->children);
        }
    }
}
