<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Domain\ValueObject;

use Phil\LicensingBundle\Domain\Catalog\Requirement;
use Phil\LicensingBundle\Domain\Exception\InvalidLicensing;
use Phil\LicensingBundle\Domain\Text;

/**
 * A license of the catalog: its code, its name, where its text is found, its SPDX identifier when it has one, and what
 * it requires.
 */
final readonly class License
{
    /**
     * @param list<Requirement> $requirements in the order of the cases, each once
     */
    private function __construct(
        public string $code,
        public string $name,
        public array $requirements,
        public ?string $url,
        public ?string $reference,
        public ?string $spdx,
    ) {
    }

    /**
     * @param list<Requirement> $requirements
     * @param string|null       $reference    where the text is registered or published when no address gives it
     *
     * @throws InvalidLicensing
     */
    public static function of(string $code, string $name, array $requirements, ?string $url = null, ?string $reference = null, ?string $spdx = null): self
    {
        return new self(
            Text::code($code, 'license code'),
            Text::required($name, 255, 'license name'),
            array_values(array_filter(Requirement::cases(), static fn (Requirement $requirement): bool => \in_array($requirement, $requirements, true))),
            Text::url($url, 'license address'),
            Text::optional($reference, 255, 'license reference'),
            Text::optional($spdx, 64, 'SPDX identifier'),
        );
    }

    public function requires(Requirement $requirement): bool
    {
        return \in_array($requirement, $this->requirements, true);
    }

    /**
     * Whether the license says where its text is found, by an address or a reference.
     */
    public function isLocated(): bool
    {
        return null !== $this->url || null !== $this->reference;
    }
}
