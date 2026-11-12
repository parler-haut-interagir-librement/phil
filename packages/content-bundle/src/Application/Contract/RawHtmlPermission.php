<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Contract;

/**
 * Whether the author at work holds the trusted role that writes raw HTML: the body they write records it
 * (`Body::$rawHtml`), shows its raw HTML in the draft, and keeps it as `raw` blocks when it is published, by them or
 * anyone else. The bundle implements it with `RawHtmlRole`, wrapped in `AuthorRawHtml`, when
 * `phil_content.raw_html_role` is set; without an implementation, nobody writes raw HTML.
 */
interface RawHtmlPermission
{
    public function isGranted(): bool;
}
