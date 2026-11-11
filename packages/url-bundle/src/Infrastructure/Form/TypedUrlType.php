<?php

declare(strict_types=1);

namespace Phil\UrlBundle\Infrastructure\Form;

use Override;
use Phil\Url\TypedUrl;
use Phil\UrlBundle\Application\AcceptedUrlTypes;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A web address in one text field, to and from a `TypedUrl`: its kind is detected among the `types` the field
 * accepts — every international kind by default —, and an address of no accepted kind is invalid. An address typed
 * without its scheme is read as `https://`.
 *
 * @extends AbstractType<TypedUrl|null>
 */
final class TypedUrlType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new TypedUrlTransformer(AcceptedUrlTypes::sets($options['types'])));
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'default_protocol' => 'https',
            'invalid_message' => 'This address is not one of the accepted kinds.',
            'attr' => ['inputmode' => 'url'],
        ]);

        $resolver
            ->define('types')
            ->default(null)
            ->info('The kinds accepted: an EnumSet, an enum class, cases, or a list of these; every international kind by default.');
    }

    #[Override]
    public function getParent(): string
    {
        return UrlType::class;
    }
}
