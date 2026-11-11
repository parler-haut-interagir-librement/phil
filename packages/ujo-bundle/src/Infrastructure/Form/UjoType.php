<?php

declare(strict_types=1);

namespace Phil\UjoBundle\Infrastructure\Form;

use Override;
use Phil\Ujo\Document;
use Phil\Ujo\Ujo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A field holding a UJO document as JSON — the value a block editor writes into it —, checked when submitted: the
 * model is the canonical JSON by default, or a `Document` with `document: true`.
 *
 * @extends AbstractType<Document|string|null>
 */
final class UjoType extends AbstractType
{
    public function __construct(
        private readonly Ujo $ujo,
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new UjoTransformer($this->ujo, true === $options['document']));
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'invalid_message' => 'This value is not a valid UJO document.',
        ]);

        $resolver
            ->define('document')
            ->default(false)
            ->allowedTypes('bool')
            ->info('Whether the model is a Document rather than its canonical JSON.');
    }

    #[Override]
    public function getParent(): string
    {
        return TextareaType::class;
    }
}
