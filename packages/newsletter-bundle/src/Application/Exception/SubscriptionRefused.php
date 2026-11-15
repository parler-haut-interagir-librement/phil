<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Exception;

use Phil\EmailAddress\Problem;
use Phil\NewsletterBundle\Domain\Exception\NewsletterException;
use RuntimeException;

/**
 * An address the newsletter refuses: not an address, a disposable one, a domain that receives no mail.
 */
final class SubscriptionRefused extends RuntimeException implements NewsletterException
{
    /**
     * @param list<Problem> $problems
     */
    public function __construct(
        public readonly array $problems,
        public readonly ?string $suggestion = null,
    ) {
        parent::__construct(\sprintf('The address is refused: %s.', implode(', ', array_map(static fn (Problem $problem): string => $problem->value, $problems))));
    }
}
