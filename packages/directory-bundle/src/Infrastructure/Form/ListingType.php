<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Infrastructure\Form;

use Override;
use Phil\Address\Address;
use Phil\Address\AddressArray;
use Phil\AddressBundle\Infrastructure\Form\AddressType;
use Phil\Contact\ContactCard;
use Phil\Contact\ContactCardArray;
use Phil\ContactBundle\Infrastructure\Form\ContactCardType;
use Phil\Geo\Coordinates;
use Phil\GeoBundle\Infrastructure\Form\CoordinatesType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A listing as its author writes it: its name, its slug, its summary, its description in Markdown, how to reach it, its
 * address, its place on a map, and the listings it is related to — a choice per relation of its genre, given by the
 * option `relations`. `toContent()` gives the data of content-bundle; `fromContent()` the form's from a draft.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class ListingType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('excerptTitle', TextType::class, ['label' => 'phil_directory.name'])
            ->add('slug', TextType::class, ['label' => 'phil_directory.slug', 'required' => false])
            ->add('excerptDescription', TextareaType::class, ['label' => 'phil_directory.summary', 'required' => false])
            ->add('bodySource', TextareaType::class, ['label' => 'phil_directory.description', 'required' => false])
            ->add('contact', ContactCardType::class, ['label' => false, 'required' => false])
            ->add('address', AddressType::class, ['label' => false, 'required' => false])
            ->add('location', CoordinatesType::class, ['label' => 'phil_directory.location', 'required' => false]);

        /** @var list<array{key: string, title: string, multiple: bool, choices: array<string, string>}> $relations */
        $relations = $options['relations'];

        if ([] === $relations) {
            return;
        }

        $related = $builder->create('related', FormType::class, ['label' => false, 'required' => false]);

        foreach ($relations as $relation) {
            $field = $builder->create($relation['key'], ChoiceType::class, [
                'label' => $relation['title'],
                'translation_domain' => false,
                'choices' => array_keys($relation['choices']),
                'choice_label' => static fn (string $id): string => $relation['choices'][$id] ?? $id,
                'choice_translation_domain' => false,
                'multiple' => $relation['multiple'],
                'required' => false,
            ]);

            if (!$relation['multiple']) {
                // One listing at most: the model is still a list, of one identifier or none.
                $field->addModelTransformer(new CallbackTransformer(
                    static fn (mixed $ids): ?string => \is_array($ids) && \is_string($ids[0] ?? null) ? $ids[0] : null,
                    static fn (mixed $id): array => \is_string($id) && '' !== $id ? [$id] : [],
                ));
            }

            $related->add($field);
        }

        $builder->add($related);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('translation_domain', 'phil_directory');
        $resolver->setDefault('relations', []);
        $resolver->setAllowedTypes('relations', 'array');
    }

    /**
     * The data of content-bundle a submitted form gives; a field left empty clears its value.
     *
     * @param array<string, mixed> $form
     *
     * @return array<string, mixed>
     */
    public static function toContent(array $form): array
    {
        $contact = $form['contact'] ?? null;
        $address = $form['address'] ?? null;
        $location = $form['location'] ?? null;
        $body = $form['bodySource'] ?? null;

        return self::withRelated($form, [
            'excerptTitle' => $form['excerptTitle'] ?? null,
            'slug' => $form['slug'] ?? null,
            'excerptDescription' => $form['excerptDescription'] ?? null,
            'bodySource' => $body,
            'bodyFormat' => null === $body ? null : 'markdown',
            'contact' => $contact instanceof ContactCard && !$contact->isEmpty() ? ContactCardArray::from($contact) : null,
            'address' => $address instanceof Address ? AddressArray::from($address) : null,
            'location' => $location instanceof Coordinates ? $location->toString() : null,
        ]);
    }

    /**
     * The form's data from a draft normalized by content-bundle.
     *
     * @param array<string, mixed> $draft
     *
     * @return array<string, mixed>
     */
    public static function fromContent(array $draft): array
    {
        $location = $draft['location'] ?? null;

        return self::withRelated($draft, [
            'excerptTitle' => $draft['excerptTitle'] ?? null,
            'slug' => $draft['slug'] ?? null,
            'excerptDescription' => $draft['excerptDescription'] ?? null,
            'bodySource' => $draft['bodySource'] ?? null,
            'contact' => null === ($draft['contact'] ?? null) ? null : ContactCardArray::to($draft['contact']),
            'address' => null === ($draft['address'] ?? null) ? null : AddressArray::to($draft['address']),
            'location' => \is_string($location) ? Coordinates::fromString($location) : null,
        ]);
    }

    /**
     * The related listings carried from one side to the other, when there are some: a form without relations
     * leaves a listing's alone.
     *
     * @param array<string, mixed> $from
     * @param array<string, mixed> $to
     *
     * @return array<string, mixed>
     */
    private static function withRelated(array $from, array $to): array
    {
        return \is_array($from['related'] ?? null) ? $to + ['related' => $from['related']] : $to;
    }
}
