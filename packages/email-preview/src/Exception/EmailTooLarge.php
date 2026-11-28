<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Exception;

use LengthException;

/**
 * An email whose HTML is larger than the check reads, refused before it is parsed.
 */
final class EmailTooLarge extends LengthException implements EmailPreviewException
{
    public static function html(int $bytes, int $limit): self
    {
        return new self(\sprintf('The HTML of the email holds %d bytes; at most %d are checked.', $bytes, $limit));
    }
}
