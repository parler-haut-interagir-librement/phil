<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;
use Phil\PagedMedia\Gotenberg\Exception\InvalidStage;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\Internal\Check;
use Phil\PagedMedia\Gotenberg\Profile;

/**
 * Repairs the PDF until it conforms to a profile, or to a PDF/A profile and a PDF/UA one together, and reports what it
 * could not fix in the `Gotenberg-Remediation-Residual` header (`0`: it conforms) and the fixes it undid in
 * `Gotenberg-Remediation-Rolled-Back`.
 *
 * Factur-X embeds the invoice XML given, at the level the XML declares, or repairs the invoice the PDF already carries.
 */
final readonly class Remediate implements Stage
{
    /** @var non-empty-list<Profile> */
    public array $profiles;

    /**
     * @param Profile|list<Profile> $profile
     * @param string|null           $language      the document's language as a BCP 47 tag; `en` when unset
     * @param bool                  $aggressive    allows fixes that may change how pages look
     * @param string|null           $figureAltText the alternate text of the figures that lack one
     * @param InputFile|null        $xml           Factur-X only: the invoice XML to embed
     * @param FacturXLevel|null     $level         Factur-X with an XML: the level it declares
     * @param string|null           $documentType  Factur-X with an XML: the document type; `INVOICE` when unset
     */
    public function __construct(
        Profile|array $profile,
        public ?string $language = null,
        public bool $aggressive = false,
        public ?string $figureAltText = null,
        public ?InputFile $xml = null,
        public ?FacturXLevel $level = null,
        public ?string $documentType = null,
    ) {
        $this->profiles = Profile::checked($profile);
        Check::notEmpty('A language', $language);
        $facturX = \in_array(Profile::FacturX, $this->profiles, true);

        if (!$facturX && ($xml instanceof InputFile || $level instanceof FacturXLevel || null !== $documentType)) {
            throw InvalidStage::because('An invoice XML, its level and its document type go with the Factur-X profile only.');
        }

        if ($xml instanceof InputFile) {
            Check::extension('An invoice', $xml, ['xml']);
        }

        if ((!$xml instanceof InputFile) !== (!$level instanceof FacturXLevel)) {
            throw InvalidStage::because('A Factur-X invoice XML is given with the level it declares, and a level with its XML.');
        }

        if (null !== $documentType && !$xml instanceof InputFile) {
            throw InvalidStage::because('A Factur-X document type is given with the invoice XML.');
        }
    }

    /**
     * Whether the PDF becomes a PDF/A or a Factur-X, which cannot be encrypted.
     */
    public function isArchival(): bool
    {
        return [] !== array_filter($this->profiles, static fn (Profile $profile): bool => !$profile->isAccessibility());
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::Remediate;
    }

    #[Override]
    public function options(): array
    {
        $profiles = array_map(static fn (Profile $profile): string => $profile->value, $this->profiles);

        return Check::set([
            'profile' => 1 === \count($profiles) ? $profiles[0] : $profiles,
            'language' => $this->language,
            'aggressive' => $this->aggressive ? true : null,
            'figureAltText' => $this->figureAltText,
            'xml' => $this->xml?->name,
            'level' => $this->level?->value,
            'documentType' => $this->documentType,
        ]);
    }

    #[Override]
    public function files(): array
    {
        return $this->xml instanceof InputFile ? [$this->xml] : [];
    }
}
