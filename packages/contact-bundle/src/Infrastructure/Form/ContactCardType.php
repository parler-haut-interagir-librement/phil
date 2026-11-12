<?php

declare(strict_types=1);

namespace Phil\ContactBundle\Infrastructure\Form;

use Override;
use Phil\Contact\ContactCard;
use Phil\InstantMessengerBundle\Infrastructure\Form\HandleType;
use Phil\UrlBundle\Infrastructure\Form\TypedUrlType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A contact card, to and from a `ContactCard`: its name, organisation and title, and collections of emails, phone
 * lines, web addresses and handles that the form theme lets people add to and remove from. With `structured_name`,
 * the person's name in its parts too, a `PersonNameType`, which gives the card its name when the name field is left
 * empty. Empty entries are dropped; an empty card is `null`.
 *
 * @extends AbstractType<ContactCard|null>
 */
final class ContactCardType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (['name', 'organization', 'title'] as $field) {
            $builder->add($field, TextType::class, ['label' => 'contact.'.$field, 'required' => false]);
        }

        if (true === $options['structured_name']) {
            $builder->add('structuredName', PersonNameType::class, ['label' => 'contact.structured_name', 'required' => false, 'culture' => $options['culture']]);
        }

        $collections = [
            'emails' => [EmailAddressType::class, []],
            'phones' => [PhoneLineType::class, ['region' => $options['region']]],
            'urls' => [TypedUrlType::class, ['types' => $options['url_types']]],
            'handles' => [HandleType::class, ['types' => $options['messenger_types']]],
        ];

        foreach ($collections as $field => [$type, $entryOptions]) {
            $builder->add($field, CollectionType::class, [
                'label' => 'contact.'.$field,
                'entry_type' => $type,
                'entry_options' => ['label' => false, 'block_prefix' => 'phil_contact_entry'] + $entryOptions,
                'allow_add' => true,
                'allow_delete' => true,
                'delete_empty' => true,
                'prototype' => true,
                'required' => false,
                'block_prefix' => 'phil_contact_collection',
            ]);
        }

        $builder->addModelTransformer(new ContactCardTransformer());
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'phil_contact',
            'invalid_message' => 'This contact card is not valid.',
        ]);

        $resolver->define('region')->default(null)->allowedTypes('string', 'null')
            ->info('The country phone numbers typed without their country code are read in.');
        $resolver->define('url_types')->default(null)
            ->info("The kinds of web address accepted, as phil/url-bundle's field takes them.");
        $resolver->define('messenger_types')->default(null)
            ->info("The messaging services offered, as phil/instant-messenger-bundle's field takes them.");
        $resolver->define('structured_name')->default(false)->allowedTypes('bool')
            ->info('Whether the form asks for the parts of a person name.');
        $resolver->define('culture')->default(null)->allowedTypes('string', 'null')
            ->info("The BCP 47 language tag of the names entered, as PersonNameType's culture option takes it.");
    }
}
