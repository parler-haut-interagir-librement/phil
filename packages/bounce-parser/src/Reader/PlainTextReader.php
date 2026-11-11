<?php

declare(strict_types=1);

namespace Phil\BounceParser\Reader;

use Phil\BounceParser\BounceKind;
use Phil\BounceParser\DeliveryAction;
use Phil\BounceParser\Mime\Part;
use Phil\BounceParser\Recipient;
use Phil\BounceParser\StatusCode;

/**
 * Reads a bounce written as text by a mail server that sends no delivery status notification — qmail, older
 * Exim and Sendmail, many hosted servers: a message from `MAILER-DAEMON` or `postmaster`, or with the null return path,
 * or under a subject such servers write. The recipient is the first address of the text, the status code the first
 * one in it; without a code, the kind is unknown and only a rule can tell.
 *
 * @internal
 */
final readonly class PlainTextReader
{
    private const array SUBJECTS = [
        'undelivered mail returned to sender',
        'mail delivery failed',
        'mail delivery failure',
        'delivery status notification (failure)',
        'delivery failure',
        'undeliverable',
        'undelivered mail',
        'failure notice',
        'returned mail',
        'non remis',
        'échec de la remise',
    ];

    public function __construct(
        private int $maxText,
    ) {
    }

    /**
     * @return list<Recipient>|null the recipient found, or none; `null` when the message is not a bounce
     */
    public function read(Part $message): ?array
    {
        if (!$this->isFromAMailServer($message)) {
            return null;
        }

        $text = Texts::body($message, $this->maxText);
        $address = Addresses::firstIn($text);

        if (null === $address) {
            return [];
        }

        $status = StatusCode::find($text);
        $smtpCode = 1 === preg_match('/(?:^|said:|:)\s*([45]\d\d)[ -]/m', $text, $match) ? (int) $match[1] : null;
        $kind = !$status instanceof StatusCode && null === $smtpCode ? BounceKind::Unknown : Recipient::kindOfFailure($status, $smtpCode);

        return [new Recipient($address, $kind, DeliveryAction::Failed, $status, smtpCode: $smtpCode)];
    }

    private function isFromAMailServer(Part $message): bool
    {
        $headers = $message->headers;
        $from = Addresses::ofHeader($headers->get('From'));

        if ('<>' === $headers->get('Return-Path') || (null !== $from && Addresses::isDaemon($from))) {
            return true;
        }

        $subject = mb_strtolower((string) $headers->decoded('Subject'));

        return array_any(self::SUBJECTS, static fn (string $start): bool => str_starts_with($subject, $start));
    }
}
