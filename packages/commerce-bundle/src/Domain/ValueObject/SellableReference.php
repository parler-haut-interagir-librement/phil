<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * What an order line sells, named by the module that sells it: a type (`product`, `offer`, `gift_card`...) and
 * an identifier within it. The core never reads the identifier; the `SellableResolver` that supports the type
 * does.
 *
 * The reference of a **configured line** also carries its configuration's fingerprint, so that two lines of the same
 * sellable with different option values are two lines of the order, each with its own adjustments. A resolver is
 * always asked with the reference `unconfigured()`.
 */
final readonly class SellableReference
{
    private function __construct(
        public string $type,
        public string $id,
        public ?string $configuration,
    ) {
    }

    /**
     * @param string|null $configuration the fingerprint of the line's configuration, 16 lowercase hexadecimal digits
     */
    public static function of(string $type, string $id, ?string $configuration = null): self
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $type)) {
            throw InvalidValue::because(\sprintf('"%s" is not a sellable type: up to 32 lowercase letters, digits or "_", starting with a letter.', $type));
        }

        if (1 !== preg_match('/^[\x21-\x7E]{1,128}$/D', $id)) {
            throw InvalidValue::because(\sprintf('"%s" is not a sellable identifier: 1 to 128 visible ASCII characters.', $id));
        }

        if (null !== $configuration && 1 !== preg_match('/^[0-9a-f]{16}$/D', $configuration)) {
            throw InvalidValue::because(\sprintf('"%s" is not the fingerprint of a configuration: 16 lowercase hexadecimal digits.', $configuration));
        }

        return new self($type, $id, $configuration);
    }

    /**
     * Reads a reference back from its `value()`.
     */
    public static function fromValue(string $value): self
    {
        if (1 !== preg_match('/^([a-z][a-z0-9_]{0,31})(?:\.([0-9a-f]{16}))?:(.+)$/D', $value, $parts)) {
            throw InvalidValue::because(\sprintf('"%s" is not the value of a sellable reference: "type:id" or "type.fingerprint:id".', $value));
        }

        return self::of($parts[1], $parts[3], '' === $parts[2] ? null : $parts[2]);
    }

    /**
     * The reference of the line that sells this with the configuration: the sellable itself for an empty one.
     */
    public function configuredBy(LineConfiguration $configuration): self
    {
        return new self($this->type, $this->id, $configuration->fingerprint());
    }

    /**
     * What is sold, without the configuration of a line: what a resolver, a price or a selection reads.
     */
    public function unconfigured(): self
    {
        return new self($this->type, $this->id, null);
    }

    /**
     * The reference as one string, `type:id`, or `type.fingerprint:id` for a configured line: a line's key in its
     * order. The fingerprint sits beside the type, whose characters never include a dot, so the value reads back
     * whatever the identifier holds.
     */
    public function value(): string
    {
        return $this->type.(null === $this->configuration ? '' : '.'.$this->configuration).':'.$this->id;
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id && $this->configuration === $other->configuration;
    }
}
