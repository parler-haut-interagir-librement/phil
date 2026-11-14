<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Mailer;

use Symfony\Component\Mime\Address;

/**
 * How a tenant's mail leaves: the named mailer transport it goes through, the sender it is sent from, and the address
 * replies go to. Any of them may be missing, and the application's defaults then apply.
 */
final readonly class TenantMail
{
    public function __construct(
        public ?string $transport = null,
        public ?Address $from = null,
        public ?Address $replyTo = null,
    ) {
    }
}
