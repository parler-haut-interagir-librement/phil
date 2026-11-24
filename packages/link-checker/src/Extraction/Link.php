<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Extraction;

use Phil\LinkChecker\LinkStatus;
use Phil\Url\Url;

/**
 * One link of a content, where it stands: the address as written, the absolute address it resolves to, the element and
 * the attribute that carry it, and the words a reader sees — the text of a link, the alternative text of an image.
 *
 * The address as written and the words are kept for a report: their whitespace and control characters are collapsed to
 * single spaces, and each is cut to `MAX_TEXT` characters.
 *
 * `$status` is set when the link is judged without a request: `Invalid` for an address that cannot be read, `Refused`
 * for one that is not public — an IP address, credentials, `localhost`. `$url` is then null.
 */
final readonly class Link
{
    public const int MAX_TEXT = 200;

    public function __construct(
        public string $href,
        public ?Url $url,
        public string $element,
        public string $attribute,
        public string $text = '',
        public ?LinkStatus $status = null,
    ) {
    }
}
