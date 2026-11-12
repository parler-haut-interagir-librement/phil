<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Application\Contract;

/**
 * Who fills the form in the current request: an identifier stable across their sessions and devices, or null for a
 * visitor. A visitor's drafts stay in their browser.
 */
interface DraftOwner
{
    public function current(): ?string;
}
