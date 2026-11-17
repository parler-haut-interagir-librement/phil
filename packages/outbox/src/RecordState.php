<?php

declare(strict_types=1);

namespace Phil\Outbox;

/**
 * Where a record stands: waiting for the relay, delivered to the listeners, or dead after its last attempt, kept to be
 * inspected and tried again.
 */
enum RecordState: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Dead = 'dead';
}
