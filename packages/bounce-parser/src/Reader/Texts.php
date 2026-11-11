<?php

declare(strict_types=1);

namespace Phil\BounceParser\Reader;

use Phil\BounceParser\Mime\Part;

/**
 * The texts of a message the readers and the rules look at.
 *
 * @internal
 */
final class Texts
{
    /**
     * The text parts of the message, not those of the message it returns, one after the other, up to the limit.
     */
    public static function body(Part $message, int $limit): string
    {
        $text = '';

        foreach ($message->walk() as $part) {
            if (str_starts_with($part->type, 'text/') && [] === $part->parts) {
                $text .= $part->body."\n";
            }
        }

        return substr($text, 0, $limit);
    }
}
