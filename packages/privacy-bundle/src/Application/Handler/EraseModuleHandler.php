<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Handler;

use Phil\PrivacyBundle\Application\Erasure\ErasureRequests;
use Phil\PrivacyBundle\Application\Exception\ModuleErasureFailed;
use Phil\PrivacyBundle\Application\Message\EraseModule;

/**
 * A module that fails throws `ModuleErasureFailed` from here, so that Messenger tries the message again; once it gives
 * up, `ErasureFailureListener` marks the step as needing attention.
 */
final readonly class EraseModuleHandler
{
    public function __construct(
        private ErasureRequests $requests,
    ) {
    }

    /**
     * @throws ModuleErasureFailed
     */
    public function __invoke(EraseModule $message): void
    {
        $this->requests->run($message->requestId, $message->module);
    }
}
