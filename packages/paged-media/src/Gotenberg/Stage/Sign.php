<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\Internal\Check;
use Phil\PagedMedia\Gotenberg\Signing\Certificate;
use Phil\PagedMedia\Gotenberg\Signing\CertificateSource;
use Phil\PagedMedia\Gotenberg\Signing\TimestampAuthority;

/**
 * Signs the PDF with a certificate, so readers can check who signed it and that nothing changed since; a PAdES
 * signature for the EU's eIDAS regulation, timestamped by an authority when one is given. Only a timestamp, another
 * signature or a terminal stage may follow it.
 */
final readonly class Sign implements Stage
{
    /**
     * @param string|null $reason      why the PDF is signed
     * @param string|null $location    where it is signed
     * @param string|null $contactInfo how to reach the signer
     * @param string|null $name        the signer's name
     */
    public function __construct(
        public Certificate $certificate,
        public ?string $reason = null,
        public ?string $location = null,
        public ?string $contactInfo = null,
        public ?string $name = null,
        public bool $pades = false,
        public ?TimestampAuthority $timestampAuthority = null,
    ) {
        Check::notEmpty('The reason of a signature', $reason);
        Check::notEmpty('The location of a signature', $location);
        Check::notEmpty('The contact of a signer', $contactInfo);
        Check::notEmpty('The name of a signer', $name);
    }

    /**
     * A signature with the certificate the source gives now.
     */
    public static function with(CertificateSource $source, ?string $reason = null, ?string $location = null, ?string $contactInfo = null, ?string $name = null, bool $pades = false, ?TimestampAuthority $timestampAuthority = null): self
    {
        return new self($source->certificate(), $reason, $location, $contactInfo, $name, $pades, $timestampAuthority);
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::Sign;
    }

    #[Override]
    public function options(): array
    {
        return Check::set([
            'certificate' => $this->certificate->certificate->name,
            'key' => $this->certificate->key?->name,
            'certificatePassword' => $this->certificate->password(),
            'reason' => $this->reason,
            'location' => $this->location,
            'contactInfo' => $this->contactInfo,
            'name' => $this->name,
            'pades' => $this->pades ? true : null,
            'timestampUrl' => $this->timestampAuthority?->url(),
        ]);
    }

    #[Override]
    public function files(): array
    {
        return $this->certificate->key instanceof InputFile ? [$this->certificate->certificate, $this->certificate->key] : [$this->certificate->certificate];
    }
}
