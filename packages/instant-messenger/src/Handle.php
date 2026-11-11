<?php

declare(strict_types=1);

namespace Phil\InstantMessenger;

use Override;
use Phil\InstantMessenger\Exception\InvalidHandle;
use Stringable;

/**
 * A handle on a messaging service, in its canonical form: the Telegram user name `ada`, the WhatsApp number
 * `+15145550123`.
 */
final readonly class Handle implements Stringable
{
    private function __construct(
        public MessengerType $type,
        public string $value,
    ) {
    }

    public static function of(MessengerType $type, string $input): self
    {
        return new self($type, $type->normalize($input) ?? throw InvalidHandle::notOfType($input, $type->name));
    }

    /**
     * A handle kept earlier in its canonical form — read back from a database, a cache, a message —, taken as it is:
     * it was read when it was first kept. Reading it again would refuse a number that the numbering plans of a later
     * import of `phil/phone` no longer know, and an entity that holds it could not be loaded.
     *
     * A text a person typed goes through `of()`.
     */
    public static function fromCanonical(MessengerType $type, string $value): self
    {
        return new self($type, $value);
    }

    public function display(): string
    {
        return $this->type->display($this->value);
    }

    public function link(): ?string
    {
        return $this->type->link($this->value);
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->value === $other->value;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
