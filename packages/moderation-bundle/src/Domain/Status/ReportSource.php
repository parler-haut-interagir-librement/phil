<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Domain\Status;

/**
 * Where a report comes from: a member, the watched words, the sandbox of the first post, or a temporary hiding by a
 * trusted member.
 */
enum ReportSource: string
{
    case Member = 'member';
    case WatchedWords = 'watched_words';
    case Sandbox = 'sandbox';
    case TrustedHide = 'trusted_hide';
}
