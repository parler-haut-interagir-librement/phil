<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The value of one option on an order line: **chosen** by the buyer — a text, a choice's code —, or **to draw** once
 * the order is paid, and then its draws, one per unit.
 *
 * A value the buyer gives carries nothing else; checked against its option, it is **described**: the option's label,
 * what a receipt shows for the choice, and the drawer that draws it. The description is kept with the order, so that
 * a receipt shows the option as it was sold, whatever the catalogue says later.
 */
final readonly class OptionValue
{
    private const int MAX_LENGTH = 1000;

    /**
     * @param list<string> $draws
     */
    private function __construct(
        public ?string $value,
        public ?string $label,
        public ?string $display,
        public ?string $drawer,
        public array $draws,
    ) {
    }

    /**
     * A value the buyer chose: a text, or the code of a choice.
     */
    public static function chosen(string $value): self
    {
        self::checkText($value, 'A chosen value');

        return new self($value, null, null, null, []);
    }

    /**
     * A value drawn once the order is paid: a draw option's, or a text drawn among its entries.
     */
    public static function toDraw(): self
    {
        return new self(null, null, null, null, []);
    }

    /**
     * The value as its option checked it: the option's label, what a receipt shows for it when it differs from the
     * value, and the drawer of a value to draw.
     */
    public function described(string $label, ?string $display = null, ?string $drawer = null): self
    {
        if ('' === trim($label)) {
            throw InvalidValue::because("A described option value has its option's label.");
        }

        if (null !== $display) {
            self::checkText($display, 'What a receipt shows of an option');
        }

        if ((null === $this->value) !== (null !== $drawer)) {
            throw InvalidValue::because('A value to draw names its drawer, and a chosen value none.');
        }

        if (null !== $drawer && 1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $drawer)) {
            throw InvalidValue::because(\sprintf('"%s" is not the name of a drawer: 1 to 64 visible ASCII characters.', $drawer));
        }

        return new self($this->value, $label, $display, $drawer, $this->draws);
    }

    /**
     * The value with its draws, one per unit of the line.
     *
     * @param list<string> $draws
     */
    public function withDraws(array $draws): self
    {
        if (null === $this->drawer) {
            throw InvalidValue::because('Only a described value to draw receives draws.');
        }

        if ([] === $draws) {
            throw InvalidValue::because('A draw gives at least one result.');
        }

        foreach ($draws as $draw) {
            self::checkText($draw, 'A draw');
        }

        return new self($this->value, $this->label, $this->display, $this->drawer, $draws);
    }

    public function isToDraw(): bool
    {
        return null === $this->value;
    }

    /**
     * Whether the value is still to draw: the order is not paid yet, or its draws were not made.
     */
    public function isPending(): bool
    {
        return null === $this->value && [] === $this->draws;
    }

    /**
     * What a receipt shows: the choice's label, the text, the draws joined by commas, or `null` while still to draw.
     */
    public function shown(): ?string
    {
        if (null === $this->value) {
            return [] === $this->draws ? null : implode(', ', $this->draws);
        }

        return $this->display ?? $this->value;
    }

    private static function checkText(string $text, string $what): void
    {
        if ('' === trim($text) || !mb_check_encoding($text, 'UTF-8') || mb_strlen($text) > self::MAX_LENGTH) {
            throw InvalidValue::because(\sprintf('%s is 1 to %d characters of UTF-8, not blank.', $what, self::MAX_LENGTH));
        }
    }
}
