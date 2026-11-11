<?php

declare(strict_types=1);

namespace Phil\Fonts\Licence;

use JsonSerializable;
use Phil\Fonts\EmbeddingUsage;
use Phil\Fonts\Exception\InvalidLicenceRegister;
use Phil\Fonts\Font;

/**
 * What one face says about who made it and how it may be used: its names, its makers, its licence and its embedding
 * permissions, as kept in a licence record.
 */
final readonly class FaceLicence implements JsonSerializable
{
    public function __construct(
        public string $family,
        public string $subfamily,
        public ?string $postScriptName,
        public ?string $version,
        public ?string $copyright,
        public ?string $trademark,
        public ?string $manufacturer,
        public ?string $designer,
        public ?string $vendorUrl,
        public ?string $licence,
        public ?string $licenceUrl,
        public ?string $spdx,
        public EmbeddingUsage $embedding,
        public bool $subsetting,
        public bool $bitmapOnly,
    ) {
    }

    public static function of(Font $font): self
    {
        $licence = $font->licence();

        return new self(
            $font->family(),
            $font->subfamily(),
            $font->postScriptName(),
            $font->version(),
            $font->copyright(),
            $font->trademark(),
            $font->manufacturer(),
            $font->designer(),
            $font->vendorUrl(),
            $licence->description,
            $licence->url,
            $licence->spdx(),
            $font->embedding->usage,
            $font->embedding->allowsSubsetting(),
            $font->embedding->bitmapOnly(),
        );
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $embedding = EmbeddingUsage::tryFrom(Field::string($data, 'embedding'))
            ?? throw InvalidLicenceRegister::because('The field "embedding" names no embedding usage.');

        return new self(
            Field::string($data, 'family'),
            Field::string($data, 'subfamily'),
            Field::optionalString($data, 'postScriptName'),
            Field::optionalString($data, 'version'),
            Field::optionalString($data, 'copyright'),
            Field::optionalString($data, 'trademark'),
            Field::optionalString($data, 'manufacturer'),
            Field::optionalString($data, 'designer'),
            Field::optionalString($data, 'vendorUrl'),
            Field::optionalString($data, 'licence'),
            Field::optionalString($data, 'licenceUrl'),
            Field::optionalString($data, 'spdx'),
            $embedding,
            Field::bool($data, 'subsetting'),
            Field::bool($data, 'bitmapOnly'),
        );
    }

    /**
     * @return array<string, string|bool|null>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array<string, string|bool|null>
     */
    public function toArray(): array
    {
        return [
            'family' => $this->family,
            'subfamily' => $this->subfamily,
            'postScriptName' => $this->postScriptName,
            'version' => $this->version,
            'copyright' => $this->copyright,
            'trademark' => $this->trademark,
            'manufacturer' => $this->manufacturer,
            'designer' => $this->designer,
            'vendorUrl' => $this->vendorUrl,
            'licence' => $this->licence,
            'licenceUrl' => $this->licenceUrl,
            'spdx' => $this->spdx,
            'embedding' => $this->embedding->value,
            'subsetting' => $this->subsetting,
            'bitmapOnly' => $this->bitmapOnly,
        ];
    }
}
