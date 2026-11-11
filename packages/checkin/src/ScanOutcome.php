<?php

declare(strict_types=1);

namespace Phil\Checkin;

/**
 * What a scan decided. Only an admission and a check-out change the attendance of the credential; every other outcome
 * is a refusal the staff explains to the holder.
 */
enum ScanOutcome: string
{
    /** The holder goes in. */
    case Admitted = 'admitted';

    /** The holder goes out; with re-entry, they may come back. */
    case CheckedOut = 'checked_out';

    /** The credential has used its entries, or its holder is inside and has not checked out. */
    case AlreadyAdmitted = 'already_admitted';

    /** A check-out of a holder who is not inside. */
    case NotInside = 'not_inside';

    /** The credential opens later. */
    case NotYetValid = 'not_yet_valid';

    /** The credential has closed. */
    case Expired = 'expired';

    /** The credential was revoked. */
    case Revoked = 'revoked';

    /** The credential does not open this checkpoint. */
    case WrongCheckpoint = 'wrong_checkpoint';

    /** No credential has this code. */
    case Unknown = 'unknown';

    /**
     * The scan identifier is recorded for another scan — another credential, checkpoint, device or direction: nothing
     * is recorded or given back, and the device sends the scan again under a new identifier.
     */
    case Collision = 'collision';

    /**
     * Whether the outcome changes the attendance: an admission or a check-out.
     */
    public function changesAttendance(): bool
    {
        return self::Admitted === $this || self::CheckedOut === $this;
    }
}
