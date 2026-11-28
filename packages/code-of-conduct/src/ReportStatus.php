<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct;

/**
 * Where a report stands. It is received, reviewed, then resolved with an outcome or dismissed; a report closed may be
 * reviewed again.
 */
enum ReportStatus: string
{
    case Received = 'received';

    case InReview = 'in_review';

    case Resolved = 'resolved';

    case Dismissed = 'dismissed';

    public function canBecome(self $next): bool
    {
        return match ($this) {
            self::Received => self::InReview === $next || self::Dismissed === $next,
            self::InReview => self::Resolved === $next || self::Dismissed === $next,
            self::Resolved, self::Dismissed => self::InReview === $next,
        };
    }

    public function isClosed(): bool
    {
        return self::Resolved === $this || self::Dismissed === $this;
    }
}
