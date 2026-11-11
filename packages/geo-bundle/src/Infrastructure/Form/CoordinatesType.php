<?php

declare(strict_types=1);

namespace Phil\GeoBundle\Infrastructure\Form;

use Override;
use Phil\Geo\Coordinates;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A position as two fields, latitude and longitude in decimal degrees, to and from `Phil\Geo\Coordinates`; both empty
 * is no position.
 *
 * @extends AbstractType<Coordinates|null>
 */
final class CoordinatesType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (['latitude', 'longitude'] as $field) {
            $builder->add($field, NumberType::class, [
                'label' => false,
                'required' => $options['required'],
                // Without a scale, a number field keeps three decimals: about a hundred metres.
                'scale' => 7,
                'html5' => true,
                'attr' => ['step' => 'any', 'inputmode' => 'decimal'],
            ]);
        }

        $builder->addModelTransformer(new CoordinatesTransformer());
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'compound' => true,
            'error_bubbling' => false,
            'invalid_message' => 'This value is not a valid position.',
        ]);
    }
}
