<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Domain\Finder;

/**
 * The answers given to the license finder so far. Each answer returns new choices; an unanswered question is `null`,
 * except the adapted material, whose answer may be `null` itself: no share-alike material.
 */
final readonly class LicenseChoices
{
    private function __construct(
        public bool $adaptedMaterialAnswered = false,
        public ?string $adaptedLicense = null,
        public ?bool $attribution = null,
        public ?bool $commercialUse = null,
        public ?Modifications $modifications = null,
    ) {
    }

    public static function none(): self
    {
        return new self();
    }

    /**
     * @param string|null $license the code of the share-alike license of the material the work adapts, or `null` when
     *                             it adapts none
     */
    public function withAdaptedLicense(?string $license): self
    {
        return new self(true, $license, $this->attribution, $this->commercialUse, $this->modifications);
    }

    public function withAttribution(bool $required): self
    {
        return new self($this->adaptedMaterialAnswered, $this->adaptedLicense, $required, $this->commercialUse, $this->modifications);
    }

    public function withCommercialUse(bool $allowed): self
    {
        return new self($this->adaptedMaterialAnswered, $this->adaptedLicense, $this->attribution, $allowed, $this->modifications);
    }

    public function withModifications(Modifications $modifications): self
    {
        return new self($this->adaptedMaterialAnswered, $this->adaptedLicense, $this->attribution, $this->commercialUse, $modifications);
    }
}
