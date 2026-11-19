<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Domain\ValueObject;

use Override;
use Phil\CalendarBundle\Domain\Exception\InvalidEvent;
use Stringable;

/**
 * A person of the application, named as hosts are: a kind — `member`, `user` — and a reference within it. The calendar
 * knows nothing more of them: who they are, and whether they may act, is the application's.
 */
final readonly class Member implements Stringable
{
    private function __construct(
        public string $kind,
        public string $reference,
    ) {
    }

    public static function of(string $kind, string $reference): self
    {
        foreach (['kind' => $kind, 'reference' => $reference] as $name => $value) {
            if (1 !== preg_match('/^[\w.:-]{1,64}$/', $value)) {
                throw InvalidEvent::because(\sprintf('The %s of a member is 1 to 64 letters, digits, "_", ".", ":" or "-".', $name));
            }
        }

        return new self($kind, $reference);
    }

    /**
     * `kind:reference`, as it is kept.
     */
    public static function fromString(string $member): self
    {
        $at = strpos($member, ':');

        return false === $at ? throw InvalidEvent::because(\sprintf('"%s" is not a member, kind:reference.', mb_substr($member, 0, 100))) : self::of(substr($member, 0, $at), substr($member, $at + 1));
    }

    public function equals(self $other): bool
    {
        return $this->kind === $other->kind && $this->reference === $other->reference;
    }

    public function toString(): string
    {
        return $this->kind.':'.$this->reference;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->toString();
    }
}
