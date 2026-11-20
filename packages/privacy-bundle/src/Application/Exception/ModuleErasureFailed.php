<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Exception;

use RuntimeException;

/**
 * What a worker throws when one module of an erasure request failed, so that Messenger tries the message again.
 *
 * It names the request and the module, and nothing else: Messenger keeps the message of an exception, and of every
 * previous one, in the envelopes it sends to the retry and failure transports, and the module's own failure can quote
 * the person's data. It never chains that failure.
 */
final class ModuleErasureFailed extends RuntimeException
{
    public static function of(string $requestId, string $module): self
    {
        return new self(\sprintf('The module "%s" of the erasure request %s failed; the step is given back.', $module, $requestId));
    }
}
