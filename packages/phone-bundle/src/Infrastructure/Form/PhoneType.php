<?php

declare(strict_types=1);

namespace Phil\PhoneBundle\Infrastructure\Form;

use Override;
use Phil\Phone\PhoneNumber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A phone number in one field, to and from a `PhoneNumber`: typed with its country code, or without it in the
 * field's `region` — the bundle's `default_region` unless the field says otherwise —; a number in no numbering plan
 * is invalid. The field holds at most `PhoneNumber::MAX_INPUT` characters.
 *
 * @extends AbstractType<PhoneNumber|null>
 */
final class PhoneType extends AbstractType
{
    public function __construct(
        private readonly ?string $defaultRegion = null,
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ?string $region */
        $region = $options['region'];
        $builder->addModelTransformer(new PhoneNumberTransformer($region));
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'invalid_message' => 'This value is not a valid phone number.',
            'attr' => ['autocomplete' => 'tel', 'maxlength' => PhoneNumber::MAX_INPUT],
        ]);

        $resolver
            ->define('region')
            ->default($this->defaultRegion)
            ->allowedTypes('string', 'null')
            ->info('The country a number typed without its country code is read in, by its ISO 3166-1 alpha-2 code.');
    }

    #[Override]
    public function getParent(): string
    {
        return TelType::class;
    }
}
