<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * How an order is offered as a gift: wrapped or not, in recycled wrap or not, with a message for the person who
 * receives it, and with its prices hidden from the documents that travel with the goods.
 *
 * One wrapping covers the whole order. The message is printed on the documents that travel with the goods, never
 * on the receipt; hidden prices leave the amounts off those documents, never off the receipt the buyer keeps.
 */
final readonly class GiftOptions
{
    /** The longest message, in characters. */
    public const int MESSAGE_LENGTH = 500;

    private function __construct(
        public bool $wrapped,
        public bool $recycledWrap,
        public ?string $message,
        public bool $pricesHidden,
    ) {
    }

    /**
     * @param bool        $wrapped      whether the order is wrapped
     * @param bool        $recycledWrap whether the wrapping is the recycled one; only for a wrapped order
     * @param string|null $message      up to 500 characters, line breaks allowed, no other control character
     * @param bool        $pricesHidden whether the documents that travel with the goods leave the amounts off
     */
    public static function of(bool $wrapped = false, bool $recycledWrap = false, ?string $message = null, bool $pricesHidden = false): self
    {
        if ($recycledWrap && !$wrapped) {
            throw InvalidValue::because('A recycled wrap is a wrapping: the order is wrapped, or its wrap is not recycled.');
        }

        if (null !== $message) {
            if ('' === trim($message) || mb_strlen($message) > self::MESSAGE_LENGTH || 1 !== preg_match('//u', $message) || 1 === preg_match('/[^\P{Cc}\n]/u', $message)) {
                throw InvalidValue::because(\sprintf('A gift message holds 1 to %d characters of text, line breaks allowed.', self::MESSAGE_LENGTH));
            }
        }

        return new self($wrapped, $recycledWrap, $message, $pricesHidden);
    }

    /**
     * @param array{wrapped?: bool, recycled_wrap?: bool, message?: string|null, prices_hidden?: bool} $stored
     */
    public static function fromArray(array $stored): self
    {
        return self::of($stored['wrapped'] ?? false, $stored['recycled_wrap'] ?? false, $stored['message'] ?? null, $stored['prices_hidden'] ?? false);
    }

    /**
     * @return array{wrapped: bool, recycled_wrap: bool, message: string|null, prices_hidden: bool}
     */
    public function toArray(): array
    {
        return ['wrapped' => $this->wrapped, 'recycled_wrap' => $this->recycledWrap, 'message' => $this->message, 'prices_hidden' => $this->pricesHidden];
    }
}
