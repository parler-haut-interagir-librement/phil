<?php

declare(strict_types=1);

namespace Phil\BounceParser\Reader;

use Phil\BounceParser\Mime\Part;

/**
 * Recognises an automatic reply: an `Auto-Submitted` field other than `no` (RFC 3834), or one of the fields mail
 * programs add to theirs — `X-Autoreply`, `X-Autorespond`, `Precedence: auto_reply`.
 *
 * @internal
 */
final class AutoReplyReader
{
    public static function isAutoReply(Part $message): bool
    {
        $headers = $message->headers;
        $autoSubmitted = $headers->get('Auto-Submitted');

        return (null !== $autoSubmitted && 'no' !== strtolower(trim((string) strtok($autoSubmitted, ';('))))
            || $headers->has('X-Autoreply')
            || $headers->has('X-Autorespond')
            || 'auto_reply' === strtolower((string) $headers->get('Precedence'));
    }
}
