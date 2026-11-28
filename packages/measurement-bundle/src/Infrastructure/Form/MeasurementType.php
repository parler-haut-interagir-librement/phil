<?php

declare(strict_types=1);

namespace Phil\MeasurementBundle\Infrastructure\Form;

use Locale;
use Override;
use Phil\Measurement\ChannelConversions;
use Phil\Measurement\Cldr\UnitFormatter;
use Phil\Measurement\Cldr\UnitWidth;
use Phil\Measurement\Exception\MeasurementException;
use Phil\Measurement\Measurement;
use Phil\Measurement\Unit;
use Phil\Measurement\UnitRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A `Measurement` in a form: a `value` text field in the request's locale and, when more than one unit is offered, a
 * `unit` choice labelled in the locale.
 *
 * ```php
 * $builder->add('weight', MeasurementType::class, ['family' => 'mass']);                    // every unit of the family
 * $builder->add('weight', MeasurementType::class, ['units' => ['kg', 'g', '[lb_av]']]);
 * $builder->add('weight', MeasurementType::class, ['unit' => 'kg']);                        // one unit, no choice
 * $builder->add('weight', MeasurementType::class, ['family' => 'mass', 'channel' => 'storefront']);
 * ```
 *
 * The value is read as a string, never a float. An empty field starts on the unit of the `channel` for the family when
 * it is offered, on the first offered unit otherwise. Add `MeasurementRange` and `MeasurementUnit` to bound what is
 * submitted.
 *
 * @extends AbstractType<Measurement|null>
 */
final class MeasurementType extends AbstractType
{
    public function __construct(
        private readonly UnitRegistry $registry,
        private readonly ChannelConversions $conversions,
        private readonly UnitFormatter $formatter,
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var non-empty-list<string> $offered */
        $offered = $options['units'];
        /** @var ?string $channel */
        $channel = $options['channel'];
        $first = $this->registry->unit($offered[0]);
        $preferred = null === $channel ? null : $this->conversions->unitFor($channel, $first->family);
        $default = $preferred instanceof Unit && \in_array($preferred->code, $offered, true) ? $preferred : $first;

        $builder->add('value', TextType::class, [
            'label' => false,
            'required' => $options['required'],
            'attr' => ['inputmode' => 'decimal'],
        ]);

        if (1 < \count($offered)) {
            $builder->add('unit', ChoiceType::class, [
                'label' => false,
                'choices' => array_combine($offered, $offered),
                'choice_label' => fn (string $code): string => $this->formatter->unitName($this->registry->unit($code)->cldr, Locale::getDefault(), UnitWidth::Short) ?? $code,
                'choice_translation_domain' => false,
            ]);
        }

        $builder->addModelTransformer(new MeasurementTransformer($this->registry, $offered, $default));
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'compound' => true,
            'error_bubbling' => false,
            'invalid_message' => 'This value is not a valid measurement.',
        ]);

        $resolver
            ->define('family')
            ->default(null)
            ->allowedTypes('null', 'string')
            ->info('The family whose units are offered, when neither "units" nor "unit" is given.');

        $resolver
            ->define('unit')
            ->default(null)
            ->allowedTypes('null', 'string')
            ->info('One unit, offered without a choice.');

        $resolver
            ->define('channel')
            ->default(null)
            ->allowedTypes('null', 'string')
            ->info('The sales channel whose unit an empty field starts on.');

        $resolver
            ->define('units')
            ->default(null)
            ->allowedTypes('null', 'string[]')
            ->info('The codes of the units offered, all of one family; those of "family" by default.')
            ->normalize($this->offered(...));
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'phil_measurement';
    }

    /**
     * @param Options<array<string, mixed>> $options
     * @param list<string>|null             $codes
     *
     * @return non-empty-list<string>
     */
    private function offered(Options $options, ?array $codes): array
    {
        /** @var ?string $unit */
        $unit = $options['unit'];
        /** @var ?string $family */
        $family = $options['family'];

        try {
            $codes = match (true) {
                null !== $unit => [$this->registry->unit($unit)->code],
                null !== $codes => array_values(array_unique($codes)),
                null !== $family => array_keys($this->registry->family($family)->units),
                default => throw new InvalidOptionsException('A measurement field names its "family", its "units" or its "unit".'),
            };
            $families = array_unique(array_map(fn (string $code): string => $this->registry->unit($code)->family, $codes));
        } catch (MeasurementException $measurementException) {
            throw new InvalidOptionsException($measurementException->getMessage(), previous: $measurementException);
        }

        if ([] === $codes || 1 !== \count($families) || (null !== $family && $family !== reset($families))) {
            throw new InvalidOptionsException('The units of a measurement field are one unit or more, all of its family.');
        }

        return $codes;
    }
}
