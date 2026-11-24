<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Domain\ValueObject;

use Phil\Address\Address;
use Phil\Address\AddressArray;
use Phil\Address\Exception\AddressException;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * Where a subscription's shipments go: a postal address, the delivery method that carries them, and a telephone number
 * kept only when that method's carrier asks for one.
 *
 * The telephone number is written as E.164 (`+15145550123`): the application reads what the subscriber typed, with
 * `phil/phone` for instance, and hands the normalised number.
 */
final readonly class DeliveryAddress
{
    private const string PHONE = '/^\+[1-9]\d{6,14}$/D';

    private const string METHOD = '/^[a-z0-9][a-z0-9_.:-]{0,63}$/D';

    private function __construct(
        public Address $address,
        public string $method,
        public ?string $phone,
    ) {
    }

    /**
     * @param string      $method the delivery method's code: lower-case letters, digits, `_`, `.`, `:` and `-`, 64 at
     *                            most
     * @param string|null $phone  E.164, or `null`
     */
    public static function of(Address $address, string $method, ?string $phone = null): self
    {
        if (1 !== preg_match(self::METHOD, $method)) {
            throw InvalidValue::because('A delivery method is named by 1 to 64 lower-case letters, digits, "_", ".", ":" or "-".');
        }

        if (null !== $phone && 1 !== preg_match(self::PHONE, $phone)) {
            throw InvalidValue::because('A telephone number of a delivery address is written as E.164, such as +15145550123.');
        }

        return new self($address, $method, $phone);
    }

    /**
     * The same address and method, without the telephone number.
     */
    public function withoutPhone(): self
    {
        return new self($this->address, $this->method, null);
    }

    /**
     * @return array{address: array<string, string>, method: string, phone: ?string}
     */
    public function toArray(): array
    {
        return ['address' => AddressArray::from($this->address), 'method' => $this->method, 'phone' => $this->phone];
    }

    /**
     * Reads back what `toArray()` wrote.
     */
    public static function fromArray(mixed $data): self
    {
        if (!\is_array($data) || !\is_string($data['method'] ?? null)) {
            throw InvalidValue::because('A stored delivery address is an array with a method.');
        }

        $phone = $data['phone'] ?? null;

        if (null !== $phone && !\is_string($phone)) {
            throw InvalidValue::because('The telephone number of a stored delivery address is a text.');
        }

        try {
            $address = AddressArray::to($data['address'] ?? null);
        } catch (AddressException $addressException) {
            throw InvalidValue::because($addressException->getMessage());
        }

        return self::of($address, $data['method'], $phone);
    }

    public function equals(self $other): bool
    {
        return $this->address->equals($other->address) && $this->method === $other->method && $this->phone === $other->phone;
    }
}
