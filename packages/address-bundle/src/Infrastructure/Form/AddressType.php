<?php

declare(strict_types=1);

namespace Phil\AddressBundle\Infrastructure\Form;

use Locale;
use Override;
use Phil\Address\Address;
use Phil\Address\AddressField;
use Phil\Address\AddressFormats;
use Phil\Address\AddressValidator;
use Phil\Address\AddressViolation;
use Phil\AddressBundle\Application\CountryFields;
use Phil\AddressBundle\Infrastructure\Validator\ValidAddress;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Intl\Countries;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A postal address, to and from an `Address`: the country, then the fields of that country's format, in its order,
 * named as it names them — state, province, prefecture —, required as it requires them; the first level of
 * subdivisions as a choice where the country has them. The fields of other countries are rendered hidden and
 * disabled, so that the Stimulus controller `phil--address-bundle--address` shows them when the country changes,
 * reading the format endpoint at a URL that names the current language and, with `with_recipient`, asks for the
 * recipient's fields; on submission, the fields follow the country submitted.
 *
 * An address edited keeps only what the country submitted writes: the values of hidden fields, which a change of
 * country leaves from the previous one, are dropped, and so are the recipient's fields the form does not show when
 * that country does not write them. The address is then checked against its country, each wrong field getting its
 * error; an error on a field the form does not show goes to the form itself.
 *
 * @extends AbstractType<Address|null>
 */
final class AddressType extends AbstractType
{
    public function __construct(
        private readonly ?string $defaultCountry = null,
        private readonly ?string $formatUrl = null,
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ?list<string> $countries */
        $countries = $options['countries'];
        /** @var ?string $default */
        $default = $options['default_country'];
        $recipient = true === $options['with_recipient'];

        $builder->add('country', CountryType::class, [
            'label' => 'address.field.country',
            'placeholder' => '',
            'choice_filter' => null === $countries ? null : static fn (?string $code): bool => null === $code || \in_array($code, $countries, true),
            'attr' => ['data-phil--address-bundle--address-target' => 'country', 'data-action' => 'phil--address-bundle--address#change', 'autocomplete' => 'country'],
        ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($default, $recipient): void {
            $data = $event->getData();
            $this->fields($event->getForm(), $data instanceof Address ? $data->country : $default, $recipient);
        });
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($recipient): void {
            $data = $event->getData();
            $data = \is_array($data) ? $data : [];

            $country = \is_string($data['country'] ?? null) ? strtoupper(trim($data['country'])) : null;

            if (null !== $country) {
                $event->setData(['country' => $country] + $data);
            }

            $this->fields($event->getForm(), $country, $recipient);
        });
        $builder->addEventListener(FormEvents::SUBMIT, $this->keepUsedFields(...));
        $builder->addEventListener(FormEvents::POST_SUBMIT, $this->check(...));
        $builder->addModelTransformer(new AddressTransformer());
    }

    #[Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        if (null !== $this->formatUrl) {
            $query = http_build_query(['locale' => Locale::getDefault()] + (true === $options['with_recipient'] ? ['recipient' => 1] : []));
            $url = $this->formatUrl.(str_contains($this->formatUrl, '?') ? '&' : '?').$query;
            $attr = $view->vars['attr'];
            $view->vars['attr'] = [...(\is_array($attr) ? $attr : []), 'data-controller' => 'phil--address-bundle--address', 'data-phil--address-bundle--address-url-value' => $url];
        }
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'phil_address',
            'invalid_message' => 'This address is not valid.',
        ]);

        $resolver->define('default_country')->default($this->defaultCountry)->allowedTypes('string', 'null')
            ->info("The country of a new address, by its ISO 3166-1 alpha-2 code; the bundle's default_country when null.");
        $resolver->define('countries')->default(null)->allowedTypes('string[]', 'null')
            ->info('The countries offered, by their codes; every country when null.');
        $resolver->define('with_recipient')->default(false)->allowedTypes('bool')
            ->info('Whether the fields that name the recipient — names, organisation — are shown.');
    }

    /**
     * The fields of a country, those of other countries hidden and disabled.
     *
     * @param FormInterface<mixed> $form
     */
    private function fields(FormInterface $form, ?string $country, bool $recipient): void
    {
        $code = null === $country ? null : strtoupper($country);
        $country = null !== $code && Countries::exists($code) ? $code : null;
        $used = [];

        foreach (null === $country ? [] : CountryFields::of($country, $recipient) as $field) {
            $used[$field['name']] = $field;
        }

        foreach (AddressField::cases() as $field) {
            if (!$recipient && \in_array($field, AddressField::recipient(), true)) {
                continue;
            }

            $shown = $used[$field->value] ?? null;
            $subdivisions = AddressField::AdministrativeArea === $field && null !== $country ? CountryFields::subdivisions($country, Locale::getDefault()) : [];
            $options = [
                'label' => $shown['label'] ?? 'address.field.'.$field->value,
                'required' => $shown['required'] ?? false,
                'disabled' => null === $shown,
                'row_attr' => ['data-phil--address-bundle--address-target' => 'row', 'data-field' => $field->value] + (null === $shown ? ['hidden' => 'hidden'] : []),
            ];

            $form->add($field->value, [] === $subdivisions ? TextType::class : ChoiceType::class, [] === $subdivisions ? $options : [
                ...$options,
                'choices' => array_column($subdivisions, 'code', 'name'),
                'choice_translation_domain' => false,
                'placeholder' => '',
            ]);
        }
    }

    /**
     * Drops from the submitted fields those of hidden fields, which the data mapper leaves at their previous value, and
     * the recipient's fields the form does not show when the country submitted does not write them.
     */
    private function keepUsedFields(FormEvent $event): void
    {
        $data = $event->getData();

        if (!\is_array($data)) {
            return;
        }

        $form = $event->getForm();
        $country = \is_string($data['country'] ?? null) && Countries::exists($data['country']) ? $data['country'] : null;
        $written = null === $country ? [] : array_map(static fn (AddressField $field): string => $field->value, AddressFormats::of($country)->fields());
        $kept = [];

        foreach ($data as $name => $value) {
            $shown = $form->has((string) $name) && !$form->get((string) $name)->isDisabled();

            if ('country' === $name || $shown || (!$form->has((string) $name) && \in_array($name, $written, true))) {
                $kept[$name] = $value;
            }
        }

        $event->setData($kept);
    }

    private function check(FormEvent $event): void
    {
        $form = $event->getForm();
        $address = $form->getData();

        if (!$address instanceof Address) {
            return;
        }

        foreach (AddressValidator::violations($address) as $violation) {
            $name = $violation->field->value;
            $field = $form->has($name) && !$form->get($name)->isDisabled() ? $form->get($name) : $form;
            $field->addError(new FormError($this->message($violation)));
        }
    }

    private function message(AddressViolation $violation): string
    {
        $message = ValidAddress::MESSAGES[$violation->violation->value];

        return $this->translator?->trans($message, [], 'validators') ?? $message;
    }
}
