<?php

declare(strict_types=1);

namespace Phil\Lti\Message;

use Phil\Lti\Support\Fields;

/**
 * The instance of the platform a launch comes from, as it describes itself.
 */
final readonly class ToolPlatform
{
    public function __construct(
        public string $guid,
        public ?string $name = null,
        public ?string $productFamilyCode = null,
        public ?string $version = null,
        public ?string $url = null,
        public ?string $contactEmail = null,
        public ?string $description = null,
    ) {
    }

    /**
     * @internal
     */
    public static function read(Fields $claim): self
    {
        return new self(
            $claim->string('guid', 255),
            $claim->optionalString('name'),
            $claim->optionalString('product_family_code'),
            $claim->optionalString('version'),
            $claim->optionalString('url'),
            $claim->optionalString('contact_email'),
            $claim->optionalString('description'),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toClaim(): array
    {
        return array_filter([
            'guid' => $this->guid,
            'name' => $this->name,
            'product_family_code' => $this->productFamilyCode,
            'version' => $this->version,
            'url' => $this->url,
            'contact_email' => $this->contactEmail,
            'description' => $this->description,
        ], static fn (?string $value): bool => null !== $value);
    }
}
