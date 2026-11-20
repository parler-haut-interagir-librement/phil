<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Outbox;

use Override;
use Phil\Outbox\Contract\Outbox;
use Phil\PrivacyBundle\Application\Contract\ErasureMessages;
use Phil\PrivacyBundle\Application\Message\EraseModule;

/**
 * The messages of the erasure workflow as records of the outbox, of type `phil_privacy.erase_module`, in the transaction
 * the caller runs: `EraseModuleCodec` rebuilds each as an `EraseModule` message.
 */
final readonly class OutboxErasureMessages implements ErasureMessages
{
    public function __construct(
        private Outbox $outbox,
    ) {
    }

    #[Override]
    public function record(string $requestId, string $module, string $idempotencyKey): void
    {
        $this->outbox->record(EraseModule::TYPE, ['request' => $requestId, 'module' => $module], null, $idempotencyKey);
    }
}
