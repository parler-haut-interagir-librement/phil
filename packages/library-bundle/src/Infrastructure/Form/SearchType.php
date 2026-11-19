<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\Form;

use Override;
use Phil\LibraryBundle\Application\Search\SearchCriteria;
use Phil\LibraryBundle\Domain\Status\TitleKind;
use Phil\LibraryBundle\Domain\ValueObject\Record;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SearchType as TextSearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Range;

/**
 * The search of the catalogue, sent by GET so that a page of results is a link: words, a kind, players, minutes, an
 * age, and the titles on the shelf only. It works without JavaScript, and carries no CSRF token, since it changes
 * nothing.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class SearchType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('q', TextSearchType::class, ['label' => 'search.text', 'required' => false, 'constraints' => [new Length(max: SearchCriteria::TEXT)]])
            ->add('kind', EnumType::class, ['label' => 'search.kind', 'class' => TitleKind::class, 'required' => false, 'placeholder' => 'search.any_kind', 'choice_label' => static fn (TitleKind $kind): string => 'kind.'.$kind->value])
            ->add('players', IntegerType::class, ['label' => 'search.players', 'required' => false, 'constraints' => [new Range(min: 1, max: Record::MAX_PLAYERS)]])
            ->add('minutes', IntegerType::class, ['label' => 'search.minutes', 'required' => false, 'constraints' => [new Range(min: 1, max: Record::MAX_MINUTES)]])
            ->add('age', IntegerType::class, ['label' => 'search.age', 'required' => false, 'constraints' => [new Range(min: 0, max: Record::MAX_AGE)]])
            ->add('available', CheckboxType::class, ['label' => 'search.available', 'required' => false]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['method' => 'GET', 'csrf_protection' => false, 'translation_domain' => 'phil_library', 'allow_extra_fields' => true]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return '';
    }
}
