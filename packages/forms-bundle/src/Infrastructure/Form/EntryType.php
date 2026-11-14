<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Infrastructure\Form;

use Override;
use Phil\FormsBundle\Domain\Contract\ConditionEvaluator;
use Phil\FormsBundle\Domain\Contract\FieldSealer;
use Phil\FormsBundle\Domain\Exception\UnsealedSensitiveField;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\Logic\BasicConditions;
use Phil\FormsBundle\Domain\Model\Form;
use Phil\FormsBundle\Domain\ValueObject\Field;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The Symfony form of a form defined in the database, field by field, protected against spam by
 * `phil/spam-protection-bundle` with its text fields inspected. Its data is an array of strings and booleans, which
 * the form definition checks once more when the entry is taken. The option `tiers` keeps the fields of those tiers;
 * `full_choices`, by field, the choices whose places are all taken, offered disabled. A field asked under a condition
 * is rendered: the definition keeps its value only when the condition holds. With `$browserConditions` — true when
 * `phil/form-logic` is installed —, the row of each such field names it in `data-form-logic-field`, and the view's
 * `phil_form_logic_steps` holds the conditions as the steps of that library's Stimulus controller, which the page's
 * templates write on the form so that the browser shows and hides the fields as they are filled; the server judges
 * again. A form with a sensitive field is not built without a `FieldSealer`, unless `$allowUnsealed` keeps sensitive
 * values as sent: the visitor is not asked for a value that would be kept in the clear.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class EntryType extends AbstractType
{
    public function __construct(
        private readonly ?FieldSealer $sealer = null,
        private readonly bool $allowUnsealed = false,
        private readonly bool $browserConditions = false,
        private readonly ConditionEvaluator $logic = new BasicConditions(),
    ) {
    }

    /**
     * @throws UnsealedSensitiveField when the form has a sensitive field and no sealer is configured
     */
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Form $form */
        $form = $options['phil_form'];
        $form->checkSealing($this->sealer, $this->allowUnsealed);
        /** @var list<string> $tiers */
        $tiers = $options['tiers'];
        /** @var array<string, list<string>> $full */
        $full = $options['full_choices'];

        foreach ($form->fieldsFor($tiers) as $field) {
            $builder->add($field->name, $this->typeOf($field), $this->optionsOf($field, $full[$field->name] ?? []));
        }
    }

    /**
     * The steps of the conditions of the fields shown, or null when the browser has none to apply.
     */
    #[Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        /** @var Form $definition */
        $definition = $options['phil_form'];
        /** @var list<string> $tiers */
        $tiers = $options['tiers'];
        $steps = $this->browserConditions ? new LogicSteps($this->logic)->of($definition->fieldsFor($tiers)) : [];
        $view->vars['phil_form_logic_steps'] = [] === $steps ? null : $steps;
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('phil_form');
        $resolver->setAllowedTypes('phil_form', Form::class);
        $resolver->setDefault('tiers', []);
        $resolver->setAllowedTypes('tiers', 'string[]');
        $resolver->setDefault('full_choices', []);
        $resolver->setAllowedTypes('full_choices', 'array');
        $resolver->setDefault('spam_protection', static function (Options $options): array {
            /** @var Form $form */
            $form = $options['phil_form'];
            $content = [];

            foreach ($form->fields() as $field) {
                if (FieldType::Text === $field->type || FieldType::Textarea === $field->type) {
                    $content[] = $field->name;
                }
            }

            return ['scope' => 'form-'.$form->key, 'content_fields' => $content];
        });
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'phil_form_entry';
    }

    /**
     * @return class-string<AbstractType<mixed>>
     */
    private function typeOf(Field $field): string
    {
        return match ($field->type) {
            FieldType::Text => TextType::class,
            FieldType::Textarea => TextareaType::class,
            FieldType::Email => EmailType::class,
            FieldType::Url => UrlType::class,
            FieldType::Date, FieldType::BirthDate => DateType::class,
            FieldType::Choice => ChoiceType::class,
            FieldType::Checkbox => CheckboxType::class,
        };
    }

    /**
     * @param list<string> $full the choices whose places are all taken
     *
     * @return array<string, mixed>
     */
    private function optionsOf(Field $field, array $full): array
    {
        $options = ['label' => $field->label, 'required' => $field->required];

        if (null !== $field->maxLength) {
            $options['attr'] = ['maxlength' => $field->maxLength];
        }

        if ($this->browserConditions && null !== $field->shownWhen) {
            $options['row_attr'] = ['data-form-logic-field' => $field->name];
        }

        return match ($field->type) {
            FieldType::Date, FieldType::BirthDate => [...$options, 'widget' => 'single_text', 'input' => 'string'],
            FieldType::Choice => [
                ...$options,
                'choices' => array_combine($field->choices, $field->choices),
                'placeholder' => $field->required ? null : '',
                'choice_attr' => static fn (string $choice): array => \in_array($choice, $full, true) ? ['disabled' => 'disabled'] : [],
            ],
            default => $options,
        };
    }
}
