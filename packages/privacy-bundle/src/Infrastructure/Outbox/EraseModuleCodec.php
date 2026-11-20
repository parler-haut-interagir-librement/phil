<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Outbox;

use Override;
use Phil\Outbox\Contract\EventCodec;
use Phil\Outbox\Exception\ForgottenRecord;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\Payload;
use Phil\PrivacyBundle\Application\Contract\ErasureRequestStore;
use Phil\PrivacyBundle\Application\Message\EraseModule;
use Phil\PrivacyBundle\Domain\Erasure\ErasureRequest;

/**
 * Rebuilds the `EraseModule` message of a module's outbox record from its identifiers: the request and the module. A
 * record whose request is gone, removed with what it was about, is skipped instead of relayed.
 */
final readonly class EraseModuleCodec implements EventCodec
{
    public function __construct(
        private ErasureRequestStore $store,
    ) {
    }

    #[Override]
    public function types(): array
    {
        return [EraseModule::TYPE];
    }

    #[Override]
    public function decode(OutboxRecord $record, Payload $payload): EraseModule
    {
        $request = $payload->string('request');

        if (1 !== preg_match(ErasureRequest::ID_PATTERN, $request) || !$this->store->find($request) instanceof ErasureRequest) {
            throw ForgottenRecord::because(\sprintf('The erasure request %s is gone.', mb_strimwidth($request, 0, 32, '…')));
        }

        return new EraseModule($request, $payload->string('module'));
    }
}
