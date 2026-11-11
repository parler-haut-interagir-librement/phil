<?php

declare(strict_types=1);

namespace Phil\BounceParser;

/**
 * One recipient a report speaks of: the address, what happened to the message, the status code, the server's own
 * words, and the kind of bounce they make.
 */
final readonly class Recipient
{
    /**
     * @param string      $address           the final recipient, the address the server tried, in lowercase domain
     * @param string|null $originalRecipient the address as the sender gave it, when the report keeps it
     * @param string|null $diagnostic        the server's own words, `smtp; 550 5.1.1 No such user`
     * @param int|null    $smtpCode          the basic SMTP reply code of the diagnostic, `550`
     * @param string|null $lastAttempt       the date of the last attempt, as written
     */
    public function __construct(
        public string $address,
        public BounceKind $kind,
        public ?DeliveryAction $action = null,
        public ?StatusCode $status = null,
        public ?string $originalRecipient = null,
        public ?string $diagnostic = null,
        public ?int $smtpCode = null,
        public ?string $remoteMta = null,
        public ?string $lastAttempt = null,
    ) {
    }

    /**
     * The kind of bounce a failure makes from its status code, or from its SMTP reply code when it has none. A
     * permanent failure about the message rather than the mailbox is soft; a failure without any code is hard, as the
     * action `failed` says no attempt follows.
     */
    public static function kindOfFailure(?StatusCode $status, ?int $smtpCode): BounceKind
    {
        if ($status instanceof StatusCode) {
            return $status->isPermanent() && !$status->isAboutTheMessage() ? BounceKind::Hard : BounceKind::Soft;
        }

        return null !== $smtpCode && $smtpCode < 500 ? BounceKind::Soft : BounceKind::Hard;
    }
}
