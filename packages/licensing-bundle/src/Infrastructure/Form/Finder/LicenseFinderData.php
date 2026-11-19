<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Infrastructure\Form\Finder;

use Phil\LicensingBundle\Domain\Finder\LicenseChoices;
use Phil\LicensingBundle\Domain\Finder\Modifications;
use Phil\LicensingBundle\Domain\Finder\Question;

/**
 * The answers of the license finder's form, kept between its steps: the current step, then one property per question.
 */
final class LicenseFinderData
{
    /** The answer to the adapted material that says the work adapts no share-alike material. */
    public const string NO_ADAPTED_MATERIAL = 'none';

    public string $step = '';

    /** The code of the share-alike license of the material the work adapts, or `none`. */
    public ?string $adaptedLicense = null;

    public ?bool $attribution = null;

    public ?bool $commercialUse = null;

    public ?Modifications $modifications = null;

    /**
     * Whether the work adapts share-alike material, whose license then answers everything else.
     */
    public function adaptsShareAlikeMaterial(): bool
    {
        return null !== $this->adaptedLicense && self::NO_ADAPTED_MATERIAL !== $this->adaptedLicense;
    }

    /**
     * The answers as the finder reads them.
     */
    public function choices(): LicenseChoices
    {
        $choices = LicenseChoices::none();

        if (null !== $this->adaptedLicense) {
            $choices = $choices->withAdaptedLicense($this->adaptsShareAlikeMaterial() ? $this->adaptedLicense : null);
        }

        if (null !== $this->attribution) {
            $choices = $choices->withAttribution($this->attribution);
        }

        if (null !== $this->commercialUse) {
            $choices = $choices->withCommercialUse($this->commercialUse);
        }

        if ($this->modifications instanceof Modifications) {
            $choices = $choices->withModifications($this->modifications);
        }

        return $choices;
    }

    /**
     * The steps of the form, one per question, in the order the finder asks them.
     *
     * @return list<string>
     */
    public static function steps(): array
    {
        return array_map(static fn (Question $question): string => $question->value, Question::cases());
    }
}
