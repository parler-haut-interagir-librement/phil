<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Status;

/**
 * What a line of the journal records.
 */
enum JournalAction: string
{
    case SiteRegistered = 'site.registered';
    case SiteRemoved = 'site.removed';
    case PushCreated = 'push.created';
    case PushDelivered = 'push.delivered';
    case UpdatePlanned = 'update.planned';
    case UpdateStep = 'update.step';
    case KeyRotationStarted = 'key.started';
    case KeyStaged = 'key.staged';
    case KeySwitched = 'key.switched';
    case KeyRetired = 'key.retired';
    case KeyRotationFinished = 'key.finished';
}
