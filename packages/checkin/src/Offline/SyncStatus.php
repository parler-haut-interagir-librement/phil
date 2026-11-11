<?php

declare(strict_types=1);

namespace Phil\Checkin\Offline;

/**
 * What became of one scan of an offline journal.
 */
enum SyncStatus: string
{
    /** Recorded, the ledger deciding as the device did. */
    case Recorded = 'recorded';

    /** Recorded with the device's decision, the ledger deciding otherwise: two devices admitted one entry, say. */
    case Conflict = 'conflict';

    /** Already recorded under its identifier, by an earlier synchronisation. */
    case Duplicate = 'duplicate';

    /**
     * Not recorded: its identifier is recorded for another scan — another credential, checkpoint, device, direction
     * or outcome, or a scan made online.
     */
    case Collision = 'collision';

    /** Not recorded: its credential is no longer known, or its instant is out of bounds. */
    case Rejected = 'rejected';
}
