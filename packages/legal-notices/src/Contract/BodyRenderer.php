<?php

declare(strict_types=1);

namespace Phil\LegalNotices\Contract;

use Phil\LegalNotices\Exception\InvalidLegalNotice;

/**
 * Turns the body of a version, written in a format, into HTML that is safe to show.
 */
interface BodyRenderer
{
    /**
     * @throws InvalidLegalNotice when the format is unknown or the body cannot be read in it
     */
    public function toHtml(string $format, string $body): string;
}
