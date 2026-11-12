<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Contract;

/**
 * Whether the user at work holds the trusted role that writes raw HTML: the draft they save shows its raw HTML, and the
 * publication they make keeps it as `raw` blocks. The bundle implements it with `RawHtmlRole` when
 * `phil_content.raw_html_role` is set; without an implementation, nobody writes raw HTML.
 */
interface RawHtmlPermission
{
    public function isGranted(): bool;
}
