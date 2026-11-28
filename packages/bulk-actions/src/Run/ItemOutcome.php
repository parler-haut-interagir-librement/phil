<?php

declare(strict_types=1);

namespace Phil\BulkActions\Run;

/**
 * What became of one item of a run.
 */
enum ItemOutcome: string
{
    /** Not handled yet. */
    case Pending = 'pending';

    /** Changed, in a committed transaction. */
    case Succeeded = 'succeeded';

    /** Refused by the voter of the action on this item, or by the loss of the right to run the action. */
    case Denied = 'denied';

    /** Changed by someone else since the person saw it. */
    case Conflict = 'conflict';

    /** Refused by the action itself, with its reason. */
    case Refused = 'refused';

    /** Stopped by an error, rolled back. */
    case Failed = 'failed';

    /** Gone, or no longer of the target's class. */
    case Missing = 'missing';

    /**
     * The translation key of the outcome, in the domain the application chooses.
     */
    public function translationKey(): string
    {
        return 'bulk_actions.outcome.'.$this->value;
    }

    /**
     * Whether a retry handles the item again: a failure always, a conflict when the retry says so.
     */
    public function isRetryable(bool $conflicts): bool
    {
        return self::Failed === $this || ($conflicts && self::Conflict === $this);
    }
}
