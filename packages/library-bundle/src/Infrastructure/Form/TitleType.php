<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\Form;

use Override;
use Phil\LibraryBundle\Domain\Status\TitleKind;
use Phil\LibraryBundle\Domain\ValueObject\Record;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Isbn;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

/**
 * The record of a title, as the catalogue's form edits it; the record checks the whole again when it is made.
 *
 * @extends AbstractType<TitleData>
 */
final class TitleType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('kind', EnumType::class, ['label' => 'record.kind', 'class' => TitleKind::class, 'choice_label' => static fn (TitleKind $kind): string => 'kind.'.$kind->value])
            ->add('name', TextType::class, ['label' => 'record.name', 'constraints' => [new NotBlank(), new Length(max: Record::NAME)]])
            ->add('isbn', TextType::class, ['label' => 'record.isbn', 'required' => false, 'constraints' => [new Isbn()]])
            ->add('creators', TextType::class, ['label' => 'record.creators', 'required' => false, 'constraints' => [new Length(max: Record::NAME)]])
            ->add('publisher', TextType::class, ['label' => 'record.publisher', 'required' => false, 'constraints' => [new Length(max: Record::NAME)]])
            ->add('year', IntegerType::class, ['label' => 'record.year', 'required' => false, 'constraints' => [new Range(min: 1000, max: 9999)]])
            ->add('minPlayers', IntegerType::class, ['label' => 'record.min_players', 'required' => false, 'constraints' => [new Range(min: 1, max: Record::MAX_PLAYERS)]])
            ->add('maxPlayers', IntegerType::class, ['label' => 'record.max_players', 'required' => false, 'constraints' => [new Range(min: 1, max: Record::MAX_PLAYERS)]])
            ->add('minMinutes', IntegerType::class, ['label' => 'record.min_minutes', 'required' => false, 'constraints' => [new Range(min: 1, max: Record::MAX_MINUTES)]])
            ->add('maxMinutes', IntegerType::class, ['label' => 'record.max_minutes', 'required' => false, 'constraints' => [new Range(min: 1, max: Record::MAX_MINUTES)]])
            ->add('minAge', IntegerType::class, ['label' => 'record.min_age', 'required' => false, 'constraints' => [new Range(min: 0, max: Record::MAX_AGE)]])
            ->add('language', TextType::class, ['label' => 'record.language', 'required' => false, 'constraints' => [new Length(max: 35)]])
            ->add('summary', TextareaType::class, ['label' => 'record.summary', 'required' => false, 'constraints' => [new Length(max: Record::SUMMARY)]]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => TitleData::class, 'translation_domain' => 'phil_library', 'csrf_token_id' => 'phil_library_title']);
    }
}
