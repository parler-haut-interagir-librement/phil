<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Form;

use Override;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\Repository\MediaRepository;
use Phil\MediaBundle\Domain\ValueObject\MediaId;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * A field that holds a medium: a hidden input with its identifier, the chosen file's name and thumbnail, a button that
 * opens the explorer's picker in a dialog, and one that empties the field. Its data is a `MediaId`, as a column of
 * type `phil_media` keeps it; `collection` opens the picker on a collection. Pair it with the `MediaFile` constraint
 * to limit what may be chosen.
 *
 * @extends AbstractType<MediaId|null>
 */
final class MediaPickerType extends AbstractType
{
    public function __construct(
        private readonly MediaRepository $media,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            static fn (mixed $value): string => match (true) {
                $value instanceof MediaId => $value->value,
                $value instanceof Media => $value->id,
                default => '',
            },
            static function (mixed $value): ?MediaId {
                if (!\is_string($value) || '' === $value) {
                    return null;
                }

                try {
                    return MediaId::fromString($value);
                } catch (InvalidMedia $invalidMedia) {
                    throw new TransformationFailedException($invalidMedia->getMessage(), previous: $invalidMedia, invalidMessage: 'This is not a medium.');
                }
            },
        ));
    }

    #[Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $value = $form->getViewData();
        $media = \is_string($value) ? $this->media->get($value) : null;
        $collection = $options['collection'];

        $view->vars['media'] = $media;
        $view->vars['picker_url'] = $this->urls->generate('phil_media_admin', \is_string($collection) ? ['picker' => 1, 'collection' => $collection] : ['picker' => 1]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['collection' => null, 'data_class' => null, 'invalid_message' => 'This is not a medium.']);
        $resolver->setAllowedTypes('collection', ['null', 'string']);
    }

    #[Override]
    public function getParent(): string
    {
        return HiddenType::class;
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'phil_media_picker';
    }
}
