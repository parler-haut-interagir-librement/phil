<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Contract;

use Phil\AnalyticsBundle\Domain\Sharing\SharedView;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;

/**
 * Where the shared views of a site are kept: the token's digest, never the token.
 */
interface SharedViews
{
    public function save(SharedView $view): void;

    public function linkOfTokenDigest(string $tokenDigest): ?SharedView;

    public function publicBoardOf(SiteId $site): ?SharedView;
}
