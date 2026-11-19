<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Application;

use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Catalog\ResourceKind;
use Phil\LicensingBundle\Domain\Model\LicenseDeclaration;
use Phil\LicensingBundle\Domain\ValueObject\License;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;

/**
 * What the license and attribution page of a work shows, ready for a template, a UJO (Universal JSON Output) document
 * or JSON-LD: the license, the declarations, the resources grouped by kind, every copyright notice once, and every
 * license the page names.
 */
final readonly class AttributionPage
{
    /**
     * @param list<AttributedResource> $resources the text sources, then the images, the maps, the fonts and the others,
     *                                            each kind in the order of the declaration
     * @param list<string>             $notices   the work's notice, then those of the resources, each once
     * @param list<License>            $licenses  the work's license, then those of the resources, each once
     */
    private function __construct(
        public SubjectReference $subject,
        public string $licenseCode,
        public ?License $license,
        public ?string $notice,
        public ?string $reservedMaterial,
        public ?string $productIdentity,
        public ?string $designatedContent,
        public ?string $licenseText,
        public array $resources,
        public array $notices,
        public array $licenses,
    ) {
    }

    public static function of(LicenseDeclaration $declaration, LicenseCatalog $catalog): self
    {
        $attributions = $declaration->attributions();
        $resources = [];

        foreach (ResourceKind::cases() as $kind) {
            foreach ($attributions as $attribution) {
                if ($attribution->kind === $kind) {
                    $resources[] = new AttributedResource($attribution, $catalog->find($attribution->license));
                }
            }
        }

        $license = $catalog->find($declaration->license());
        $notices = [$declaration->notice()];
        $licenses = [$license];

        foreach ($resources as $resource) {
            $notices[] = $resource->attribution->notice;
            $licenses[] = $resource->license;
        }

        $known = [];

        foreach ($licenses as $named) {
            if ($named instanceof License) {
                $known[$named->code] = $named;
            }
        }

        return new self(
            $declaration->subject(),
            $declaration->license(),
            $license,
            $declaration->notice(),
            $declaration->reservedMaterial(),
            $declaration->productIdentity(),
            $declaration->designatedContent(),
            $declaration->licenseText(),
            $resources,
            array_values(array_unique(array_filter($notices, static fn (?string $notice): bool => null !== $notice))),
            array_values($known),
        );
    }

    /**
     * The resources of one kind.
     *
     * @return list<AttributedResource>
     */
    public function resourcesOf(ResourceKind $kind): array
    {
        return array_values(array_filter($this->resources, static fn (AttributedResource $resource): bool => $resource->attribution->kind === $kind));
    }
}
