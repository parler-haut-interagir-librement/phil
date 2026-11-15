<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\CommerceBundle\Domain\Exception\InvalidConfiguration;
use Phil\CommerceBundle\Domain\Option\OptionKind;
use Phil\CommerceBundle\Domain\Option\TextSource;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;

/**
 * One option a catalogue declares on what it sells: its code and label, its kind, whether a line must set it, and what
 * it adds to the unit price — the option's own supplement, plus the supplement of the choice made.
 *
 * - **Text**: a text within its `TextConstraints`, typed, picked from its entries, or drawn among them after payment.
 * - **Choice**: one of its choices, each with its label, image and supplement.
 * - **Ink**: one of its inks, a choice printed as a layer of its own.
 * - **Draw**: always set, never chosen: drawn once the order is paid, by the drawer it names.
 * - **Amount**: an amount the buyer chooses, in minor units of its range's currency, within its `AmountRange`, added
 *   to the unit price — a price the buyer sets on a sellable priced at zero, a donation, a tip. The price still comes
 *   from the catalogue, which sets the bounds: the buyer only picks within them.
 */
final readonly class OptionDefinition
{
    /**
     * @param array<string, OptionChoice> $choices by code
     */
    private function __construct(
        public string $code,
        public string $label,
        public OptionKind $kind,
        public bool $required,
        public ?Money $supplement,
        public ?TextConstraints $text,
        public array $choices,
        public ?string $drawer,
        public ?AmountRange $range = null,
    ) {
    }

    /**
     * @param string|null $drawer the drawer of a text drawn among its entries, required with the `Drawn` source
     */
    public static function text(string $code, string $label, TextConstraints $text, bool $required = false, ?Money $supplement = null, ?string $drawer = null): self
    {
        if ($text->allows(TextSource::Drawn) !== (null !== $drawer)) {
            throw InvalidValue::because(\sprintf('The text option %s names a drawer when it may be drawn, and only then.', $code));
        }

        return self::checked($code, $label, OptionKind::Text, $required, $supplement, $text, [], $drawer);
    }

    /**
     * @param list<OptionChoice> $choices
     */
    public static function choice(string $code, string $label, array $choices, bool $required = false, ?Money $supplement = null): self
    {
        return self::checked($code, $label, OptionKind::Choice, $required, $supplement, null, self::keyed($code, $choices), null);
    }

    /**
     * @param list<OptionChoice> $inks
     */
    public static function ink(string $code, string $label, array $inks, bool $required = false, ?Money $supplement = null): self
    {
        return self::checked($code, $label, OptionKind::Ink, $required, $supplement, null, self::keyed($code, $inks), null);
    }

    /**
     * @param string $drawer the name of the drawer that draws it, as an `OptionDrawer` supports it
     */
    public static function draw(string $code, string $label, string $drawer, ?Money $supplement = null): self
    {
        return self::checked($code, $label, OptionKind::Draw, true, $supplement, null, [], $drawer);
    }

    /**
     * An amount the buyer chooses within the range, in its currency.
     */
    public static function amount(string $code, string $label, AmountRange $range, bool $required = true): self
    {
        return self::checked($code, $label, OptionKind::Amount, $required, null, null, [], null, $range);
    }

    /**
     * The value checked and described, or `null` for an optional option left unset.
     */
    public function check(?OptionValue $value): ?OptionValue
    {
        if (OptionKind::Draw === $this->kind) {
            if ($value instanceof OptionValue && !$value->isToDraw()) {
                throw InvalidConfiguration::refused($this->code, 'it is drawn after payment, not chosen');
            }

            return OptionValue::toDraw()->described($this->label, null, $this->drawer);
        }

        if (!$value instanceof OptionValue) {
            return $this->required ? throw InvalidConfiguration::required($this->code) : null;
        }

        if ($value->isToDraw()) {
            if (!$this->text instanceof TextConstraints || !$this->text->allows(TextSource::Drawn)) {
                throw InvalidConfiguration::refused($this->code, 'it is not drawn');
            }

            return OptionValue::toDraw()->described($this->label, null, $this->drawer);
        }

        $chosen = (string) $value->value;

        if ($this->range instanceof AmountRange) {
            $amount = $this->range->read($chosen) ?? throw InvalidConfiguration::refused($this->code, \sprintf('"%s" is not an amount in minor units', $chosen));
            $refusal = $this->range->refusal($amount);

            return null === $refusal
                ? OptionValue::chosen($chosen)->described($this->label, $amount->toDecimal().' '.$amount->currency->code)
                : throw InvalidConfiguration::refused($this->code, $refusal);
        }

        if ($this->text instanceof TextConstraints) {
            $refusal = $this->text->refusal($chosen);

            return null === $refusal
                ? OptionValue::chosen($chosen)->described($this->label)
                : throw InvalidConfiguration::refused($this->code, $refusal);
        }

        $choice = $this->choices[$chosen] ?? throw InvalidConfiguration::refused($this->code, \sprintf('"%s" is not one of its choices', $chosen));

        return OptionValue::chosen($chosen)->described($this->label, $choice->label);
    }

    /**
     * What the value adds to the unit price: the amount chosen, or the option's supplement and its choice's, or `null`
     * when it adds nothing.
     */
    public function supplement(OptionValue $value): ?Money
    {
        if ($this->range instanceof AmountRange) {
            $amount = null === $value->value ? null : $this->range->read($value->value);

            return $amount instanceof Money && !$amount->isZero() ? $amount : null;
        }

        $choice = null === $value->value ? null : ($this->choices[$value->value] ?? null);
        $supplements = array_filter([$this->supplement, $choice?->supplement]);

        return [] === $supplements ? null : Money::sum(...array_values($supplements));
    }

    /**
     * @param array<string, OptionChoice> $choices
     */
    private static function checked(string $code, string $label, OptionKind $kind, bool $required, ?Money $supplement, ?TextConstraints $text, array $choices, ?string $drawer, ?AmountRange $range = null): self
    {
        LineConfiguration::checkCode($code);

        if ('' === trim($label)) {
            throw InvalidValue::because(\sprintf('The option %s has no label.', $code));
        }

        if ($supplement instanceof Money && $supplement->isNegative()) {
            throw InvalidValue::because(\sprintf('The option %s has a negative supplement; a discount is a promotion.', $code));
        }

        if (null !== $drawer && 1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $drawer)) {
            throw InvalidValue::because(\sprintf('"%s" is not the name of a drawer: 1 to 64 visible ASCII characters.', $drawer));
        }

        return new self($code, $label, $kind, $required, $supplement, $text, $choices, $drawer, $range);
    }

    /**
     * @param list<OptionChoice> $choices
     *
     * @return array<string, OptionChoice>
     */
    private static function keyed(string $code, array $choices): array
    {
        if ([] === $choices) {
            throw InvalidValue::because(\sprintf('The option %s has at least one choice.', $code));
        }

        $keyed = [];

        foreach ($choices as $choice) {
            if (isset($keyed[$choice->code])) {
                throw InvalidValue::because(\sprintf('The option %s lists the choice %s twice.', $code, $choice->code));
            }

            $keyed[$choice->code] = $choice;
        }

        return $keyed;
    }
}
