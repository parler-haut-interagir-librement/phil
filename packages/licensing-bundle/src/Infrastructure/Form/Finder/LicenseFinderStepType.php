<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Infrastructure\Form\Finder;

use Override;
use Phil\LicensingBundle\Domain\Finder\LicenseFinder;
use Phil\LicensingBundle\Domain\Finder\Modifications;
use Phil\LicensingBundle\Domain\Finder\Question;
use Phil\LicensingBundle\Infrastructure\Translation\LicenseNames;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * One step of the license finder: the question of its `question` option, as radio buttons, each answer required.
 *
 * @extends AbstractType<LicenseFinderData>
 */
final class LicenseFinderStepType extends AbstractType
{
    public function __construct(
        private readonly LicenseFinder $finder,
        private readonly LicenseNames $names,
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Question $question */
        $question = $options['question'];
        $common = ['expanded' => true, 'label' => 'finder.'.$question->value, 'constraints' => [new NotNull()]];

        match ($question) {
            Question::AdaptedMaterial => $builder->add('adaptedLicense', ChoiceType::class, $common + [
                'choices' => $this->adaptedLicenses(),
                'choice_label' => fn (string $code): string => LicenseFinderData::NO_ADAPTED_MATERIAL === $code ? $this->names->trans('finder.adapted_material.none') : $this->names->trans('finder.adapted_material.license', ['%license%' => $this->names->name($code)]),
                'choice_translation_domain' => false,
                'help' => 'finder.adapted_material.help',
            ]),
            Question::Attribution => $builder->add('attribution', ChoiceType::class, $common + ['choices' => ['finder.attribution.yes' => true, 'finder.attribution.no' => false]]),
            Question::CommercialUse => $builder->add('commercialUse', ChoiceType::class, $common + ['choices' => ['finder.commercial_use.yes' => true, 'finder.commercial_use.no' => false]]),
            Question::Modifications => $builder->add('modifications', EnumType::class, $common + [
                'class' => Modifications::class,
                'choice_label' => static fn (Modifications $modifications): string => 'finder.modifications.'.$modifications->value,
            ]),
        };
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'inherit_data' => true,
            'label' => false,
            'translation_domain' => LicenseNames::DOMAIN,
        ]);
        $resolver->define('question')
            ->required()
            ->allowedTypes(Question::class)
            ->info('The question the step asks.');
    }

    /**
     * @return array<string, string>
     */
    private function adaptedLicenses(): array
    {
        $choices = [LicenseFinderData::NO_ADAPTED_MATERIAL => LicenseFinderData::NO_ADAPTED_MATERIAL];

        foreach ($this->finder->shareAlikeLicenses() as $license) {
            $choices[$license->code] = $license->code;
        }

        return $choices;
    }
}
