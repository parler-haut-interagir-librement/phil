<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Infrastructure\Form;

use Phil\SettingsBundle\Domain\Exception\InvalidSettingValue;
use Phil\SettingsBundle\Domain\Setting\SettingDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The form of one section, generated from its settings: a checkbox for a boolean, a number field for an integer, a
 * text field for a text, radio buttons for a choice and checkboxes for a list of choices, each labelled by its
 * translation keys, with no class of its own, so that the stylesheet dresses the elements. A value the setting's rules
 * refuse becomes an error on its field, in the reader's language. The form is named `phil_settings`, which is the
 * identifier of its CSRF token.
 *
 * A choice written on the page's root element carries `data-attribute` and `data-default`, and the form the
 * `phil--settings-bundle--document` controller, which applies a choice as soon as it is made.
 */
final readonly class SettingsForm
{
    public const string NAME = 'phil_settings';

    public const string CONTROLLER = 'phil--settings-bundle--document';

    public function __construct(
        private FormFactoryInterface $forms,
        private ?TranslatorInterface $translator = null,
    ) {
    }

    /**
     * @param array<SettingDefinition>                    $definitions
     * @param array<string, bool|int|string|list<string>> $values      by key
     *
     * @return FormInterface<mixed>
     */
    public function create(array $definitions, array $values): FormInterface
    {
        $data = [];
        $live = false;

        foreach ($definitions as $definition) {
            $data[self::field($definition->key)] = $values[$definition->key] ?? $definition->default;
            $live = $live || null !== $definition->htmlAttribute;
        }

        $builder = $this->forms->createNamedBuilder(self::NAME, FormType::class, $data, [
            'attr' => $live ? ['data-controller' => self::CONTROLLER, 'data-action' => 'change->'.self::CONTROLLER.'#apply'] : [],
        ]);

        foreach ($definitions as $definition) {
            $builder->add(self::field($definition->key), $this->type($definition), $this->options($definition));
        }

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($definitions): void {
            $this->check($event->getForm(), $definitions);
        });

        return $builder->getForm();
    }

    /**
     * The values submitted, by key.
     *
     * @param FormInterface<mixed>     $form
     * @param array<SettingDefinition> $definitions
     *
     * @return array<string, mixed>
     */
    public static function values(FormInterface $form, array $definitions): array
    {
        $values = [];

        foreach ($definitions as $definition) {
            $values[$definition->key] = $form->get(self::field($definition->key))->getData();
        }

        return $values;
    }

    /**
     * The name of a setting's field: its key, the dots, which a form name refuses, as hyphens, which a key never holds.
     */
    private static function field(string $key): string
    {
        return str_replace('.', '-', $key);
    }

    /**
     * @param FormInterface<mixed>     $form
     * @param array<SettingDefinition> $definitions
     */
    private function check(FormInterface $form, array $definitions): void
    {
        foreach ($definitions as $definition) {
            $field = $form->get(self::field($definition->key));

            if (!$field->isSynchronized()) {
                continue;
            }

            try {
                $definition->normalize($field->getData());
            } catch (InvalidSettingValue $invalidSettingValue) {
                $message = $this->translator?->trans($invalidSettingValue->reason, $invalidSettingValue->parameters, 'phil_settings') ?? $invalidSettingValue->getMessage();
                $field->addError(new FormError($message, $invalidSettingValue->reason, $invalidSettingValue->parameters));
            }
        }
    }

    /**
     * @return class-string<FormType|CheckboxType|IntegerType|TextType|ChoiceType>
     */
    private function type(SettingDefinition $definition): string
    {
        return match ($definition->type) {
            SettingType::Boolean => CheckboxType::class,
            SettingType::Integer => IntegerType::class,
            SettingType::Text => TextType::class,
            SettingType::Choice, SettingType::Choices => ChoiceType::class,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function options(SettingDefinition $definition): array
    {
        $options = [
            'label' => $definition->label,
            'help' => $definition->help,
            'translation_domain' => $definition->translationDomain,
            'required' => false,
        ];

        return match ($definition->type) {
            SettingType::Boolean => $options,
            SettingType::Integer => $options + ['attr' => array_filter(['min' => $definition->min, 'max' => $definition->max], static fn (?int $bound): bool => null !== $bound)],
            SettingType::Text => $options + ['empty_data' => '', 'attr' => ['maxlength' => $definition->maxLength]],
            SettingType::Choice, SettingType::Choices => $options + $this->choices($definition),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function choices(SettingDefinition $definition): array
    {
        $labels = array_map(static fn (string $choice): string => $definition->key.'.choices.'.$choice, $definition->choices);

        return [
            'choices' => array_combine($labels, $definition->choices),
            'choice_translation_domain' => $definition->translationDomain,
            'expanded' => true,
            'multiple' => SettingType::Choices === $definition->type,
            'placeholder' => false,
            'attr' => null === $definition->htmlAttribute ? [] : ['data-attribute' => $definition->htmlAttribute, 'data-default' => $definition->default],
        ];
    }
}
