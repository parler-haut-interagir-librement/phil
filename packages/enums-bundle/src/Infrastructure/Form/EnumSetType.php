<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Form;

use Override;
use Phil\Enums\Collection\EnumSet;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use UnitEnum;

/**
 * A field whose data is an `EnumSet`: the cases of the enum as checkboxes, those of the set checked.
 *
 * ```php
 * $builder->add('permissions', EnumSetType::class, ['class' => Permission::class]);
 * ```
 *
 * It is an `EnumType` with `multiple` and `expanded` set, so every option of the enum type — `group`, `only`,
 * `except`, `where_meta`, labels from metadata — applies; `expanded: false` gives a multiple select instead. An
 * empty submission is the empty set, never `null`.
 *
 * @extends AbstractType<EnumSet<UnitEnum>>
 */
final class EnumSetType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var class-string<UnitEnum> $enum */
        $enum = $options['class'];

        $builder->addModelTransformer(new CallbackTransformer(
            static function (mixed $set) use ($enum): array {
                if (null === $set) {
                    return [];
                }

                if (!$set instanceof EnumSet || $set->enum() !== $enum) {
                    throw new TransformationFailedException(\sprintf('Expected an EnumSet of %s, got %s.', $enum, get_debug_type($set)));
                }

                return $set->cases()->all();
            },
            static function (mixed $cases) use ($enum): EnumSet {
                /** @var list<UnitEnum> $list */
                $list = \is_array($cases) ? $cases : [];

                return EnumSet::of($enum, ...$list);
            },
        ));
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'multiple' => true,
            'expanded' => true,
        ]);
    }

    #[Override]
    public function getParent(): string
    {
        return EnumType::class;
    }
}
