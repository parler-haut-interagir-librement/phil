<?php

declare(strict_types=1);

namespace Phil\Fonts\Licence;

use JsonSerializable;
use Phil\Fonts\Exception\InvalidLicenceRegister;
use Phil\Fonts\FontFile;
use Phil\Fonts\Format;

/**
 * The licence record of one font file: its path, its SHA-256, which tells whether the file changed since it was
 * recorded, its format, and what each of its faces says about its licence.
 */
final readonly class LicenceRecord implements JsonSerializable
{
    /**
     * @param non-empty-list<FaceLicence> $faces
     */
    public function __construct(
        public string $file,
        public string $sha256,
        public Format $format,
        public array $faces,
    ) {
    }

    public static function of(string $file, FontFile $font): self
    {
        return new self($file, $font->sha256, $font->format, array_map(FaceLicence::of(...), $font->faces));
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $format = Format::tryFrom(Field::string($data, 'format'))
            ?? throw InvalidLicenceRegister::because('The field "format" names no font format.');
        $faces = array_map(
            static fn (mixed $face): FaceLicence => \is_array($face) ? FaceLicence::fromArray($face) : throw InvalidLicenceRegister::because('Each face must be an object.'),
            Field::list($data, 'faces'),
        );

        if ([] === $faces) {
            throw InvalidLicenceRegister::because(\sprintf('The record of "%s" has no face.', Field::string($data, 'file')));
        }

        return new self(Field::string($data, 'file'), Field::string($data, 'sha256'), $format, $faces);
    }

    /**
     * The licence of the record's first face; the faces of a collection usually share one.
     */
    public function licence(): FaceLicence
    {
        return $this->faces[0];
    }

    /**
     * @return array{file: string, sha256: string, format: string, faces: list<array<string, string|bool|null>>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array{file: string, sha256: string, format: string, faces: list<array<string, string|bool|null>>}
     */
    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'sha256' => $this->sha256,
            'format' => $this->format->value,
            'faces' => array_map(static fn (FaceLicence $face): array => $face->toArray(), $this->faces),
        ];
    }
}
