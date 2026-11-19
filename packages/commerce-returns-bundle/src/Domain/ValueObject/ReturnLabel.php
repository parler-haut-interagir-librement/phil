<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The label a carrier gave to send goods back: the carrier, the tracking number, and where the label's file is kept,
 * as the adapter that asked for it names it.
 */
final readonly class ReturnLabel
{
    private function __construct(
        public string $carrier,
        public string $tracking,
        public ?string $document,
    ) {
    }

    /**
     * @param string      $carrier  1 to 64 visible ASCII characters
     * @param string      $tracking 1 to 128 visible ASCII characters
     * @param string|null $document 1 to 255 visible ASCII characters: the adapter's own reference to the label's file
     */
    public static function of(string $carrier, string $tracking, ?string $document = null): self
    {
        if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $carrier) || 1 !== preg_match('/^[\x21-\x7E]{1,128}$/D', $tracking)) {
            throw InvalidValue::because('A return label names its carrier in 1 to 64 visible ASCII characters, and its tracking number in 1 to 128.');
        }

        if (null !== $document && 1 !== preg_match('/^[\x21-\x7E]{1,255}$/D', $document)) {
            throw InvalidValue::because('The file of a return label is named in 1 to 255 visible ASCII characters.');
        }

        return new self($carrier, $tracking, $document);
    }

    /**
     * @return array{carrier: string, tracking: string, document: ?string}
     */
    public function toArray(): array
    {
        return ['carrier' => $this->carrier, 'tracking' => $this->tracking, 'document' => $this->document];
    }

    /**
     * @param array{carrier: string, tracking: string, document: ?string} $data
     */
    public static function fromArray(array $data): self
    {
        return self::of($data['carrier'], $data['tracking'], $data['document']);
    }
}
