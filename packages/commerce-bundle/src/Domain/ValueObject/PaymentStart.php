<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * What a gateway answers when it hands a payment to its processor: the processor's reference of the payment, by
 * which its notifications will name it, and how the buyer goes on — a page to send them to, or a secret the
 * processor's script uses in the page.
 */
final readonly class PaymentStart
{
    private function __construct(
        public string $externalReference,
        public ?string $redirectUrl,
        public ?string $clientSecret,
    ) {
    }

    public static function of(string $externalReference, ?string $redirectUrl = null, ?string $clientSecret = null): self
    {
        if (1 !== preg_match('/^[\x21-\x7E]{1,255}$/D', $externalReference)) {
            throw InvalidValue::because('A payment reference is 1 to 255 visible ASCII characters.');
        }

        if (null !== $redirectUrl && 1 !== preg_match('#^https://[\x21-\x7E]+$#D', $redirectUrl)) {
            throw InvalidValue::because('A payment page is an https URL.');
        }

        if ('' === $clientSecret) {
            throw InvalidValue::because('A client secret, when given, is not empty.');
        }

        return new self($externalReference, $redirectUrl, $clientSecret);
    }
}
