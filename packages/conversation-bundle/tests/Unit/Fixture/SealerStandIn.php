<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit\Fixture;

use Override;
use Phil\ConversationBundle\Application\Contract\MessageSealer;
use Phil\ConversationBundle\Infrastructure\KeyManagement\KeyManagementMessageSealer;
use SensitiveParameter;

/**
 * The sealer of the bundle over the two keys of `Keys`, `k2026` the current one, built once for the whole test run:
 * the stores of the tests seal for real.
 */
final readonly class SealerStandIn implements MessageSealer
{
    private KeyManagementMessageSealer $inner;

    public function __construct(string $current = 'k2026')
    {
        $this->inner = Keys::sealer($current);
    }

    #[Override]
    public function seal(#[SensitiveParameter] string $text, string $context): string
    {
        return $this->inner->seal($text, $context);
    }

    #[Override]
    public function open(string $sealed, string $context): string
    {
        return $this->inner->open($sealed, $context);
    }

    #[Override]
    public function currentKey(): string
    {
        return $this->inner->currentKey();
    }
}
