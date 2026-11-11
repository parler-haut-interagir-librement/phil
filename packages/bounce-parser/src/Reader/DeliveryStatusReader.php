<?php

declare(strict_types=1);

namespace Phil\BounceParser\Reader;

use Phil\BounceParser\BounceKind;
use Phil\BounceParser\DeliveryAction;
use Phil\BounceParser\Mime\Headers;
use Phil\BounceParser\Mime\Part;
use Phil\BounceParser\Recipient;
use Phil\BounceParser\StatusCode;

/**
 * Reads the `message/delivery-status` part of a delivery status notification (RFC 3464), or its international form
 * `message/global-delivery-status` (RFC 6533): a group of fields for the message, then one for each recipient.
 *
 * @internal
 */
final readonly class DeliveryStatusReader
{
    private const array TYPES = ['message/delivery-status', 'message/global-delivery-status'];

    public function __construct(
        private int $maxHeaders,
    ) {
    }

    /**
     * @return list<Recipient>|null the recipients, or `null` when the message holds no delivery status
     */
    public function read(Part $message): ?array
    {
        foreach ($message->walk() as $part) {
            if (\in_array($part->type, self::TYPES, true)) {
                return $this->recipients($part->body);
            }
        }

        return null;
    }

    /**
     * @return list<Recipient>
     */
    private function recipients(string $body): array
    {
        $recipients = [];
        $groups = preg_split('/\r?\n[ \t]*\r?\n/', $body);

        foreach (false === $groups ? [] : $groups as $group) {
            $fields = Headers::parse($group, $this->maxHeaders);
            $address = Addresses::ofTypedField($fields->get('Final-Recipient'))
                ?? Addresses::ofTypedField($fields->get('Original-Recipient'));

            if (null !== $address) {
                $recipients[] = $this->recipient($address, $fields);
            }
        }

        return $recipients;
    }

    private function recipient(string $address, Headers $fields): Recipient
    {
        $diagnostic = $fields->get('Diagnostic-Code');
        $action = DeliveryAction::tryFrom(strtolower((string) $fields->get('Action')));
        $status = StatusCode::parse((string) strtok((string) $fields->get('Status'), " \t("))
            ?? (null === $diagnostic ? null : StatusCode::find($diagnostic));
        $smtpCode = null !== $diagnostic && 1 === preg_match('/^\s*(?:smtp\s*;)?\s*([245]\d\d)(?!\d)/i', $diagnostic, $match)
            ? (int) $match[1]
            : null;

        $kind = match (true) {
            DeliveryAction::Delayed === $action => BounceKind::Soft,
            \in_array($action, [DeliveryAction::Delivered, DeliveryAction::Relayed, DeliveryAction::Expanded], true),
            null === $action && true === $status?->isSuccess() => BounceKind::Delivered,
            default => Recipient::kindOfFailure($status, $smtpCode),
        };

        return new Recipient(
            $address,
            $kind,
            $action,
            $status,
            Addresses::ofTypedField($fields->get('Original-Recipient')),
            $diagnostic,
            $smtpCode,
            $this->afterType($fields->get('Remote-MTA')),
            $fields->get('Last-Attempt-Date'),
        );
    }

    private function afterType(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $semicolon = strpos($value, ';');

        return trim(false === $semicolon ? $value : substr($value, $semicolon + 1));
    }
}
