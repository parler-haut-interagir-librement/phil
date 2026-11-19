<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Infrastructure\Form\Finder;

use Override;
use Phil\LicensingBundle\Domain\Finder\Question;
use Phil\LicensingBundle\Infrastructure\Translation\LicenseNames;
use Symfony\Component\Form\Flow\AbstractFlowType;
use Symfony\Component\Form\Flow\DataStorage\SessionDataStorage;
use Symfony\Component\Form\Flow\FormFlowBuilderInterface;
use Symfony\Component\Form\Flow\Type\NavigatorFlowType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The license finder as a form in steps, one question a page, on the form flow of Symfony: it works without
 * JavaScript, each step posted and the answers kept between them, in the session by default.
 *
 * Material under a share-alike license skips the other questions: its license is the answer. Once finished, the data
 * gives the choices `LicenseFinder::recommend()` reads.
 */
final class LicenseFinderType extends AbstractFlowType
{
    public const string STORAGE_KEY = 'phil_licensing_finder';

    public function __construct(
        private readonly ?RequestStack $requests = null,
    ) {
    }

    #[Override]
    public function buildFormFlow(FormFlowBuilderInterface $builder, array $options): void
    {
        $adapted = static fn (LicenseFinderData $data): bool => $data->adaptsShareAlikeMaterial();

        foreach (Question::cases() as $question) {
            $builder->addStep(
                $question->value,
                LicenseFinderStepType::class,
                ['question' => $question],
                Question::AdaptedMaterial === $question ? null : $adapted,
            );
        }

        $builder->add('navigator', NavigatorFlowType::class);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LicenseFinderData::class,
            'step_property_path' => 'step',
            'translation_domain' => LicenseNames::DOMAIN,
            'data_storage' => $this->requests instanceof RequestStack ? new SessionDataStorage(self::STORAGE_KEY, $this->requests) : null,
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'phil_licensing_finder';
    }
}
