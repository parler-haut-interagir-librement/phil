<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Chain;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * What a row is indexed by, beside its payload: who acted, what was done, to which record, in which tenant. Each is an
 * identifier, never a personal value — an account is designated by its stable identifier, not by its e-mail address —,
 * and the row hash covers every one of them, so that a changed index shows like a changed payload.
 */
final readonly class Facts
{
    private function __construct(
        public ?string $action,
        public ?string $actor,
        public ?Subject $subject,
        public ?string $tenant,
    ) {
    }

    /**
     * No facts: a row found by its segment and its instant only, such as a discontinuity.
     */
    public static function none(): self
    {
        return new self(null, null, null, null);
    }

    /**
     * @param string      $action a dotted name, such as `order.paid` or `workflow.order.ship`
     * @param string|null $actor  the stable identifier of the account or the process that acted; `null` for none
     *
     * @throws InvalidValue when a value is not an identifier
     */
    public static function of(string $action, ?string $actor = null, ?Subject $subject = null, ?string $tenant = null): self
    {
        self::assertAction($action);

        if (null !== $actor) {
            self::assertIdentifier('An actor', $actor);
        }

        if (null !== $tenant) {
            self::assertTenant($tenant);
        }

        return new self($action, $actor, $subject, $tenant);
    }

    /**
     * The facts as storage holds them, unchecked: {@see self::isWellFormed()} says whether they could have been
     * written, and a row whose facts could not is not intact.
     */
    public static function restore(?string $action, ?string $actor, ?Subject $subject, ?string $tenant): self
    {
        return new self($action, $actor, $subject, $tenant);
    }

    /**
     * Whether every fact passes the checks {@see self::of()} makes: a value that does not was written around them, and
     * may move a line of the row hash into the next.
     */
    public function isWellFormed(): bool
    {
        try {
            if (null !== $this->action) {
                self::assertAction($this->action);
            }

            if (null !== $this->actor) {
                self::assertIdentifier('An actor', $this->actor);
            }

            if ($this->subject instanceof Subject) {
                Subject::of($this->subject->type, $this->subject->id);
            }

            if (null !== $this->tenant) {
                self::assertTenant($this->tenant);
            }
        } catch (InvalidValue) {
            return false;
        }

        return true;
    }

    /**
     * The same facts in a tenant; with `null`, in none.
     *
     * @throws InvalidValue when the tenant is not a reference
     */
    public function inTenant(?string $tenant): self
    {
        if (null !== $tenant) {
            self::assertTenant($tenant);
        }

        return new self($this->action, $this->actor, $this->subject, $tenant);
    }

    /**
     * The lines the row hash takes over the facts, an empty line for each one missing.
     *
     * @return list<string>
     */
    public function hashLines(): array
    {
        return [$this->action ?? '', $this->actor ?? '', $this->subject->type ?? '', $this->subject->id ?? '', $this->tenant ?? ''];
    }

    /**
     * @throws InvalidValue when the action is not 1 to 64 lower-case letters, digits, ".", "_", ":" or "-" starting
     *                      with a letter
     */
    public static function assertAction(string $action): void
    {
        if (1 !== preg_match('/^[a-z][a-z0-9._:-]{0,63}$/D', $action)) {
            throw InvalidValue::because(\sprintf('An action is 1 to 64 lower-case letters, digits, ".", "_", ":" or "-", starting with a letter; "%s" is not.', $action));
        }
    }

    /**
     * An identifier: 1 to 128 letters, digits, ".", "_", ":" or "-", starting with a letter or a digit. No "@": an
     * e-mail address is personal, and it is not stable.
     *
     * @throws InvalidValue
     */
    public static function assertIdentifier(string $what, string $value): void
    {
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/D', $value)) {
            throw InvalidValue::because(\sprintf('%s is 1 to 128 letters, digits, ".", "_", ":" or "-", starting with a letter or a digit; "%s" is not.', $what, $value));
        }
    }

    /**
     * A tenant reference: 1 to 255 bytes of UTF-8, without control characters or surrounding white space.
     *
     * @throws InvalidValue
     */
    public static function assertTenant(string $tenant): void
    {
        if (\strlen($tenant) > 255 || 1 !== preg_match('/^[^\p{Cc}\s\p{Z}](?:[^\p{Cc}]*[^\p{Cc}\s\p{Z}])?$/uD', $tenant)) {
            throw InvalidValue::because('A tenant reference is 1 to 255 bytes of UTF-8, without control characters or surrounding white space.');
        }
    }
}
