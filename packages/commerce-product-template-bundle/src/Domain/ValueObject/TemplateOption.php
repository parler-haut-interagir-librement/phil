<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Domain\ValueObject;

use Phil\CommerceBundle\Domain\Option\OptionKind;
use Phil\CommerceBundle\Domain\Option\TextSource;
use Phil\CommerceBundle\Domain\ValueObject\OptionChoice;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\PrintZone;
use Phil\CommerceBundle\Domain\ValueObject\TextConstraints;
use Phil\CommerceProductTemplate\Combination;
use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplateBundle\Domain\Exception\TemplateRefused;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Exception\MoneyException;
use Phil\Money\Money;
use ValueError;

/**
 * An option of the core of commerce declared on a template or a design: a text, a choice, an ink or a draw, offered on
 * every product, or on the products whose values its condition lists — a draw on the loot box version only, a glowing
 * ink on dark colours.
 *
 * It is kept as an array of scalars in its template or design, and read back with every value checked by the core's
 * own constructors.
 */
final readonly class TemplateOption
{
    /**
     * @param array<string, list<string>> $when value codes by axis code
     */
    private function __construct(
        public OptionDefinition $definition,
        public array $when,
    ) {
    }

    /**
     * @param array<string, list<string>> $when the products it is offered on, by axis values; every product when empty
     */
    public static function of(OptionDefinition $definition, array $when = []): self
    {
        if (OptionKind::Amount === $definition->kind) {
            throw InvalidValue::because(\sprintf('The option %s is an amount, chosen on an order line, never in a template.', $definition->code));
        }

        try {
            return new self($definition, Combination::condition($when, \sprintf('The condition of the option %s', $definition->code)));
        } catch (InvalidDefinition $invalidDefinition) {
            throw InvalidValue::because($invalidDefinition->getMessage());
        }
    }

    /**
     * Whether it is offered on a product of these values, by axis code.
     *
     * @param array<string, string> $values
     */
    public function isOfferedOn(array $values): bool
    {
        return array_all($this->when, static fn (array $codes, string $axis): bool => \in_array($values[$axis] ?? null, $codes, true));
    }

    /**
     * Options as their owner keeps them, each code once.
     *
     * @param list<self> $options
     *
     * @return list<array<string, mixed>>
     */
    public static function listToArray(array $options): array
    {
        $codes = [];

        foreach ($options as $option) {
            if (isset($codes[$option->definition->code])) {
                throw InvalidValue::because(\sprintf('The option %s is declared twice.', $option->definition->code));
            }

            $codes[$option->definition->code] = $option->definition->code;
        }

        return array_map(static fn (self $option): array => $option->toArray(), $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $definition = $this->definition;
        $text = $definition->text;

        return [
            'code' => $definition->code,
            'label' => $definition->label,
            'kind' => $definition->kind->value,
            'required' => $definition->required,
            'supplement' => self::money($definition->supplement),
            'text' => $text instanceof TextConstraints ? [
                'min_length' => $text->minLength,
                'max_length' => $text->maxLength,
                'characters' => $text->characters,
                'font' => $text->font,
                'zone' => $text->zone instanceof PrintZone ? ['name' => $text->zone->name, 'x' => $text->zone->x, 'y' => $text->zone->y, 'width' => $text->zone->width, 'height' => $text->zone->height] : null,
                'sources' => array_map(static fn (TextSource $source): string => $source->value, $text->sources),
                'entries' => $text->entries,
            ] : null,
            'choices' => array_values(array_map(static fn (OptionChoice $choice): array => [
                'code' => $choice->code,
                'label' => $choice->label,
                'image' => $choice->image,
                'supplement' => self::money($choice->supplement),
            ], $definition->choices)),
            'drawer' => $definition->drawer,
            'when' => $this->when,
        ];
    }

    /**
     * @param array<array-key, mixed> $data as `toArray()` writes it
     *
     * @throws TemplateRefused for an array no code of the module wrote
     */
    public static function fromArray(array $data): self
    {
        try {
            $code = self::string($data, 'code');
            $label = self::string($data, 'label');
            $required = $data['required'] ?? null;
            $supplement = self::readMoney($data['supplement'] ?? null);
            $drawer = self::nullableString($data, 'drawer');

            if (!\is_bool($required)) {
                throw InvalidValue::because('required is not a boolean');
            }

            $definition = match (OptionKind::from(self::string($data, 'kind'))) {
                OptionKind::Text => OptionDefinition::text($code, $label, self::text($data['text'] ?? null), $required, $supplement, $drawer),
                OptionKind::Choice => OptionDefinition::choice($code, $label, self::choices($data['choices'] ?? null), $required, $supplement),
                OptionKind::Ink => OptionDefinition::ink($code, $label, self::choices($data['choices'] ?? null), $required, $supplement),
                OptionKind::Draw => OptionDefinition::draw($code, $label, $drawer ?? throw InvalidValue::because('a draw names its drawer'), $supplement),
                OptionKind::Amount => throw InvalidValue::because('an amount is chosen on an order line, never in a template'),
            };
            $when = $data['when'] ?? null;

            return self::of($definition, \is_array($when) ? Combination::condition($when, 'The condition of an option') : throw InvalidValue::because('when is not an object'));
        } catch (ValueError|InvalidValue|InvalidDefinition|MoneyException $error) {
            throw TemplateRefused::unreadable('An option', $error->getMessage());
        }
    }

    /**
     * @return array{amount: int, currency: string}|null
     */
    private static function money(?Money $money): ?array
    {
        return $money instanceof Money ? ['amount' => $money->minorUnits, 'currency' => $money->currency->code] : null;
    }

    private static function readMoney(mixed $data): ?Money
    {
        if (null === $data) {
            return null;
        }

        if (!\is_array($data) || !\is_int($data['amount'] ?? null) || !\is_string($data['currency'] ?? null)) {
            throw InvalidValue::because('a supplement is an amount and a currency');
        }

        return Money::of($data['amount'], Currency::of($data['currency']));
    }

    private static function text(mixed $data): TextConstraints
    {
        if (!\is_array($data) || !\is_int($data['min_length'] ?? null) || !\is_int($data['max_length'] ?? null) || !\is_array($data['sources'] ?? null) || !\is_array($data['entries'] ?? null)) {
            throw InvalidValue::because('a text option has its constraints');
        }

        $zone = $data['zone'] ?? null;

        return TextConstraints::of(
            $data['max_length'],
            $data['min_length'],
            self::nullableString($data, 'characters'),
            self::nullableString($data, 'font'),
            null === $zone ? null : self::zone($zone),
            array_values(array_map(static fn (mixed $source): TextSource => TextSource::from(\is_string($source) ? $source : throw InvalidValue::because('a source is a string')), $data['sources'])),
            array_values(array_map(static fn (mixed $entry): string => \is_string($entry) ? $entry : throw InvalidValue::because('an entry is a string'), $data['entries'])),
        );
    }

    private static function zone(mixed $zone): PrintZone
    {
        if (!\is_array($zone) || !\is_string($zone['name'] ?? null) || !\is_int($zone['x'] ?? null) || !\is_int($zone['y'] ?? null) || !\is_int($zone['width'] ?? null) || !\is_int($zone['height'] ?? null)) {
            throw InvalidValue::because('a zone is a name and a rectangle of whole millimetres');
        }

        return PrintZone::of($zone['name'], $zone['x'], $zone['y'], $zone['width'], $zone['height']);
    }

    /**
     * @return list<OptionChoice>
     */
    private static function choices(mixed $choices): array
    {
        if (!\is_array($choices)) {
            throw InvalidValue::because('choices is not a list');
        }

        $list = [];

        foreach ($choices as $choice) {
            if (!\is_array($choice)) {
                throw InvalidValue::because('a choice is an object');
            }

            $list[] = OptionChoice::of(self::string($choice, 'code'), self::string($choice, 'label'), self::nullableString($choice, 'image'), self::readMoney($choice['supplement'] ?? null));
        }

        return $list;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) ? $value : throw InvalidValue::because(\sprintf('%s is not a string', $key));
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return null === $value || \is_string($value) ? $value : throw InvalidValue::because(\sprintf('%s is not a string', $key));
    }
}
