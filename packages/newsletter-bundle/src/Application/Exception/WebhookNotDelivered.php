<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Exception;

use Phil\NewsletterBundle\Domain\Exception\NewsletterException;
use RuntimeException;
use Throwable;

/**
 * An audience webhook the receiver did not accept: worth retrying, or not.
 */
final class WebhookNotDelivered extends RuntimeException implements NewsletterException
{
    public function __construct(
        string $message,
        public readonly bool $retryable,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
