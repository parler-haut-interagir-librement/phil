<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Domain\Finder;

use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Catalog\Requirement;
use Phil\LicensingBundle\Domain\Exception\InvalidLicensing;
use Phil\LicensingBundle\Domain\ValueObject\License;

/**
 * The decision tree that finds the license of a work from what its author allows.
 *
 * Material under a share-alike license imposes that license on the work that adapts it, and nothing else is asked.
 * Otherwise the author says whether credit is required, whether commercial use is allowed and what may be done with
 * adaptations. With credit, a Creative Commons 4.0 license answers each combination; without credit and with every
 * use allowed, CC0 1.0; any other combination gets custom terms written from the answers.
 */
final readonly class LicenseFinder
{
    private const array CREATIVE_COMMONS = [
        'commercial' => [
            Modifications::Allowed->value => LicenseCatalog::CC_BY,
            Modifications::ShareAlike->value => LicenseCatalog::CC_BY_SA,
            Modifications::Forbidden->value => LicenseCatalog::CC_BY_ND,
        ],
        'non_commercial' => [
            Modifications::Allowed->value => LicenseCatalog::CC_BY_NC,
            Modifications::ShareAlike->value => LicenseCatalog::CC_BY_NC_SA,
            Modifications::Forbidden->value => LicenseCatalog::CC_BY_NC_ND,
        ],
    ];

    public function __construct(
        private LicenseCatalog $catalog,
    ) {
    }

    /**
     * The licenses that impose themselves on the material adapted from a work under them, in the order of the catalog.
     *
     * @return list<License>
     */
    public function shareAlikeLicenses(): array
    {
        return array_values(array_filter($this->catalog->all(), static fn (License $license): bool => $license->requires(Requirement::ShareAlike)));
    }

    /**
     * The next question to ask, or `null` when the choices are enough for a recommendation.
     */
    public function nextQuestion(LicenseChoices $choices): ?Question
    {
        return match (true) {
            !$choices->adaptedMaterialAnswered => Question::AdaptedMaterial,
            null !== $choices->adaptedLicense => null,
            null === $choices->attribution => Question::Attribution,
            null === $choices->commercialUse => Question::CommercialUse,
            !$choices->modifications instanceof Modifications => Question::Modifications,
            default => null,
        };
    }

    /**
     * @throws InvalidLicensing when a question is unanswered, or the adapted license is not a share-alike license of
     *                          the catalog
     */
    public function recommend(LicenseChoices $choices): Recommendation
    {
        $question = $this->nextQuestion($choices);

        if ($question instanceof Question) {
            throw InvalidLicensing::because(\sprintf('The question "%s" of the license finder is unanswered.', $question->value));
        }

        if (null !== $choices->adaptedLicense) {
            return Recommendation::catalog($this->shareAlike($choices->adaptedLicense)->code, true);
        }

        // Every question is answered: nextQuestion() found none left.
        $commercialUse = true === $choices->commercialUse;
        $modifications = $choices->modifications ?? Modifications::Allowed;

        if (true === $choices->attribution) {
            return Recommendation::catalog(self::CREATIVE_COMMONS[$commercialUse ? 'commercial' : 'non_commercial'][$modifications->value]);
        }

        if ($commercialUse && Modifications::Allowed === $modifications) {
            return Recommendation::catalog(LicenseCatalog::CC0);
        }

        return Recommendation::custom(CustomTerms::of(false, $commercialUse, $modifications));
    }

    /**
     * @throws InvalidLicensing
     */
    private function shareAlike(string $code): License
    {
        foreach ($this->shareAlikeLicenses() as $license) {
            if ($license->code === $code) {
                return $license;
            }
        }

        throw InvalidLicensing::because(\sprintf('The license "%s" is not a share-alike license of the catalog.', $code));
    }
}
