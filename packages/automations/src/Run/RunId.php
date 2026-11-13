<?php

declare(strict_types=1);

namespace Phil\Automations\Run;

use Phil\Automations\Definition\AutomationId;
use Phil\Automations\Exception\InvalidPayload;
use Stringable;

/**
 * The id of a run: 32 hexadecimal digits drawn from the automation and the event key, so that one event delivered
 * twice names the same run and starts it once.
 */
final readonly class RunId implements Stringable
{
    private function __construct(
        public string $value,
    ) {
    }

    public static function for(AutomationId $automation, string $eventKey): self
    {
        return new self(substr(hash('sha256', $automation->value."\0".$eventKey), 0, 32));
    }

    /**
     * @throws InvalidPayload when the value is not 32 lowercase hexadecimal digits
     */
    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^[0-9a-f]{32}$/D', $value)) {
            throw InvalidPayload::because(\sprintf('"%s" is not a run id: 32 lowercase hexadecimal digits.', $value));
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
