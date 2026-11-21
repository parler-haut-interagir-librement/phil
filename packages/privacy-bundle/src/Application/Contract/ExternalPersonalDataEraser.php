<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Contract;

use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * One module's part of an erasure that an external service carries out, such as a mailing service or a payment
 * processor, and acknowledges later.
 *
 * `request()` sends the erasure with the idempotency key of the step, which stays the same however many times the step
 * runs: the service erases once for a key. The step ends only when the application hands the service's acknowledgement
 * to `ErasureRequests::acknowledge()` with that key, such as from the webhook the service calls.
 *
 * **Must be idempotent.** A message delivered twice, a replay, or a step put back after a failure sends the erasure
 * again, with the same key.
 */
interface ExternalPersonalDataEraser
{
    public function module(): ModuleName;

    /**
     * @param string $idempotencyKey the key of the step, made of the request's identifier and the module's name
     */
    public function request(DataSubject $subject, string $idempotencyKey): void;
}
