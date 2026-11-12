<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Infrastructure\Form;

use Override;
use Phil\AlmanacBundle\Application\DateDraft;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The form of a stored date, to a `DateDraft`: key — fixed once the date exists, with `key_locked` —, title, rule,
 * until, days, all day and tags, separated by commas. Labels come from the `phil_almanac` domain.
 *
 * @extends AbstractType<DateDraft>
 */
final class StoredDateType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('key', TextType::class, ['label' => 'phil_almanac.form.key', 'disabled' => $options['key_locked']])
            ->add('title', TextType::class, ['label' => 'phil_almanac.form.title'])
            ->add('rule', TextType::class, ['label' => 'phil_almanac.form.rule', 'help' => 'phil_almanac.form.rule_help'])
            ->add('until', TextType::class, ['label' => 'phil_almanac.form.until', 'required' => false])
            ->add('days', IntegerType::class, ['label' => 'phil_almanac.form.days', 'attr' => ['min' => 1, 'max' => 366]])
            ->add('allDay', CheckboxType::class, ['label' => 'phil_almanac.form.all_day', 'required' => false])
            ->add('tags', TextType::class, ['label' => 'phil_almanac.form.tags', 'required' => false]);

        $builder->get('tags')->addModelTransformer(new TagsTransformer());
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => DateDraft::class, 'translation_domain' => 'phil_almanac', 'key_locked' => false]);
        $resolver->setAllowedTypes('key_locked', 'bool');
    }
}
