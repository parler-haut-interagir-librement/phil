<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Domain\ValueObject;

use Phil\LicensingBundle\Domain\Catalog\ResourceKind;
use Phil\LicensingBundle\Domain\Exception\InvalidLicensing;
use Phil\LicensingBundle\Domain\Text;

/**
 * The credit of one resource a work uses: an image, a font, a map, a text source such as a system reference document.
 *
 * The notice is the one its licensor asks to keep — a copyright line, an attribution statement, the copyright notice of
 * Open Game Content —, reproduced as given. The changes say how the work modified the resource.
 *
 * @phpstan-type AttributionArray array{kind: string, title: string, license: string, author: string|null, url: string|null, notice: string|null, changes: string|null}
 */
final readonly class Attribution
{
    public const int TITLE_LENGTH = 255;

    public const int AUTHOR_LENGTH = 255;

    public const int NOTICE_LENGTH = 4000;

    public const int CHANGES_LENGTH = 1000;

    private function __construct(
        public ResourceKind $kind,
        public string $title,
        public string $license,
        public ?string $author,
        public ?string $url,
        public ?string $notice,
        public ?string $changes,
    ) {
    }

    /**
     * @param string $license the code of the resource's license in the catalog
     *
     * @throws InvalidLicensing
     */
    public static function of(ResourceKind $kind, string $title, string $license, ?string $author = null, ?string $url = null, ?string $notice = null, ?string $changes = null): self
    {
        return new self(
            $kind,
            Text::required($title, self::TITLE_LENGTH, 'resource title'),
            Text::code($license, 'license code'),
            Text::optional($author, self::AUTHOR_LENGTH, 'resource author'),
            Text::url($url, 'resource address'),
            Text::optional($notice, self::NOTICE_LENGTH, 'resource notice'),
            Text::optional($changes, self::CHANGES_LENGTH, 'resource changes'),
        );
    }

    /**
     * An attribution read back from its stored form.
     *
     * @param array<array-key, mixed> $attribution
     *
     * @throws InvalidLicensing
     */
    public static function fromArray(array $attribution): self
    {
        $kind = ResourceKind::tryFrom(self::string($attribution, 'kind') ?? '') ?? throw InvalidLicensing::because('The resource kind is unknown.');

        return self::of(
            $kind,
            self::string($attribution, 'title') ?? '',
            self::string($attribution, 'license') ?? '',
            self::string($attribution, 'author'),
            self::string($attribution, 'url'),
            self::string($attribution, 'notice'),
            self::string($attribution, 'changes'),
        );
    }

    /**
     * @return AttributionArray
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'title' => $this->title,
            'license' => $this->license,
            'author' => $this->author,
            'url' => $this->url,
            'notice' => $this->notice,
            'changes' => $this->changes,
        ];
    }

    /**
     * Whether the attribution credits someone: an author, or a notice that names them.
     */
    public function credits(): bool
    {
        return null !== $this->author || null !== $this->notice;
    }

    /**
     * @param array<array-key, mixed> $attribution
     *
     * @throws InvalidLicensing
     */
    private static function string(array $attribution, string $key): ?string
    {
        $value = $attribution[$key] ?? null;

        if (null !== $value && !\is_string($value)) {
            throw InvalidLicensing::because(\sprintf('The "%s" of an attribution is a text.', $key));
        }

        return $value;
    }
}
