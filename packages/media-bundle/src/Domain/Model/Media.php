<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Phil\CreationDisclosure\DigitalSourceType;
use Phil\CreationDisclosure\GenerativeAi;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Domain\Media\AiDisclosure;
use Phil\MediaBundle\Domain\ValueObject\FileInfo;
use Symfony\Component\Uid\Uuid;

/**
 * A medium: its current file and its version number, where it is kept, its collection, what it shows — title,
 * alternative text, credit —, its license, what it owes to artificial intelligence — and, when phil/creation-disclosure
 * is installed, how it was made as an IPTC digital source type —, its focal point, from 0 to 1 across and down, which
 * crops keep in view, and the tiny preview of its current version, when one was computed.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_media')]
final class Media
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: MediaCollection::class)]
        #[ORM\JoinColumn(name: 'collection', nullable: true, onDelete: 'SET NULL')]
        private ?MediaCollection $collection,
        #[ORM\Column(name: 'version')]
        private int $version,
        #[ORM\Column(name: 'filename', length: 255)]
        private string $filename,
        #[ORM\Column(name: 'mime_type', length: 100)]
        private string $mimeType,
        #[ORM\Column(name: 'size')]
        private int $size,
        #[ORM\Column(name: 'width', nullable: true)]
        private ?int $width,
        #[ORM\Column(name: 'height', nullable: true)]
        private ?int $height,
        #[ORM\Column(name: 'storage_key', length: 400)]
        private string $storageKey,
        #[ORM\Column(name: 'title', length: 255, nullable: true)]
        private ?string $title,
        #[ORM\Column(name: 'alt', length: 500, nullable: true)]
        private ?string $alt,
        #[ORM\Column(name: 'credit', length: 255, nullable: true)]
        private ?string $credit,
        #[ORM\Column(name: 'license', length: 64)]
        private string $license,
        #[ORM\Column(name: 'ai_disclosure', length: 16, enumType: AiDisclosure::class)]
        private AiDisclosure $aiDisclosure,
        #[ORM\Column(name: 'digital_source_type', length: 64, nullable: true)]
        private ?string $digitalSourceType,
        #[ORM\Column(name: 'focal_x')]
        private float $focalX,
        #[ORM\Column(name: 'focal_y')]
        private float $focalY,
        #[ORM\Column(name: 'updated_at')]
        private DateTimeImmutable $updatedAt,
        #[ORM\Column(name: 'placeholder', type: 'text', nullable: true)]
        private ?string $placeholder = null,
    ) {
    }

    public static function upload(FileInfo $file, ?MediaCollection $collection, DateTimeImmutable $at): self
    {
        $id = Uuid::v7()->toRfc4122();

        return new self($id, $collection, 1, $file->filename, $file->mimeType, $file->size, $file->width, $file->height, self::keyOf($id, 1, $file), null, null, null, 'all-rights-reserved', AiDisclosure::None, null, 0.5, 0.5, self::utc($at));
    }

    /**
     * Gives the medium a new file: its version goes up, and the previous one is kept as a `MediaVersion`.
     */
    public function replace(FileInfo $file, DateTimeImmutable $at): MediaVersion
    {
        $previous = MediaVersion::of($this, $this->version, FileInfo::of($this->filename, $this->mimeType, $this->size, $this->width, $this->height), $this->storageKey, $at);
        ++$this->version;
        $this->filename = $file->filename;
        $this->mimeType = $file->mimeType;
        $this->size = $file->size;
        $this->width = $file->width;
        $this->height = $file->height;
        $this->storageKey = self::keyOf($this->id, $this->version, $file);
        $this->updatedAt = self::utc($at);
        $this->placeholder = null;

        return $previous;
    }

    /**
     * Gives the current file another name, as the explorer shows it and the browser saves it; the file and its key
     * stay as they are.
     */
    public function rename(string $filename): void
    {
        $this->filename = FileInfo::of($filename, $this->mimeType, $this->size)->filename;
    }

    public function describe(?string $title, ?string $alt = null, ?string $credit = null): void
    {
        $this->title = $this->text($title, 255, 'title');
        $this->alt = $this->text($alt, 500, 'alternative text');
        $this->credit = $this->text($credit, 255, 'credit');
    }

    /**
     * @param string $license an SPDX identifier — `CC-BY-4.0`, `CC0-1.0` — or `all-rights-reserved`
     */
    public function license(string $license, AiDisclosure $aiDisclosure = AiDisclosure::None): void
    {
        if (1 !== preg_match('/^[A-Za-z0-9.+-]{1,64}$/D', $license)) {
            throw InvalidMedia::because(\sprintf('A license is an SPDX identifier or "all-rights-reserved"; "%s" is not.', $license));
        }

        $this->license = $license;
        $this->aiDisclosure = $aiDisclosure;
        $this->digitalSourceType = null;
    }

    /**
     * Declares how the medium was made, as an IPTC digital source type of phil/creation-disclosure: its AI disclosure
     * follows — `Generated` for media created with generative AI, `Assisted` for media edited with it or that may hold
     * generated elements, `None` otherwise. A retired type is declared as its replacement.
     */
    public function disclose(DigitalSourceType $type): void
    {
        $type = $type->current();
        $this->digitalSourceType = $type->value;
        $this->aiDisclosure = match ($type->generativeAi()) {
            GenerativeAi::Full => AiDisclosure::Generated,
            GenerativeAi::None => AiDisclosure::None,
            default => AiDisclosure::Assisted,
        };
    }

    /**
     * Keeps the tiny preview of the current version: a `data:` URL of a raster image in base64, 4,096 characters at
     * most. A new version empties it.
     */
    public function preview(string $placeholder): void
    {
        if (\strlen($placeholder) > 4096 || 1 !== preg_match('{^data:image/(?:webp|avif|jpeg|png);base64,[A-Za-z0-9+/]+={0,2}$}D', $placeholder)) {
            throw InvalidMedia::because('A placeholder is a data: URL of a WebP, AVIF, JPEG or PNG image in base64, 4096 characters or fewer.');
        }

        $this->placeholder = $placeholder;
    }

    public function focus(float $x, float $y): void
    {
        if ($x < 0 || $x > 1 || $y < 0 || $y > 1) {
            throw InvalidMedia::because('A focal point is from 0 to 1 across and down.');
        }

        $this->focalX = $x;
        $this->focalY = $y;
    }

    public function moveTo(?MediaCollection $collection): void
    {
        $this->collection = $collection;
    }

    public function file(): FileInfo
    {
        return FileInfo::of($this->filename, $this->mimeType, $this->size, $this->width, $this->height);
    }

    public function version(): int
    {
        return $this->version;
    }

    public function storageKey(): string
    {
        return $this->storageKey;
    }

    public function collection(): ?MediaCollection
    {
        return $this->collection;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function alt(): ?string
    {
        return $this->alt;
    }

    public function credit(): ?string
    {
        return $this->credit;
    }

    public function licenseId(): string
    {
        return $this->license;
    }

    public function aiDisclosure(): AiDisclosure
    {
        return $this->aiDisclosure;
    }

    /**
     * The IPTC code of how the medium was made, `trainedAlgorithmicMedia`; `null` when only its AI disclosure was given.
     */
    public function digitalSourceType(): ?string
    {
        return $this->digitalSourceType;
    }

    /**
     * The tiny preview of the current version as a `data:` URL; `null` until one is computed.
     */
    public function placeholder(): ?string
    {
        return $this->placeholder;
    }

    /**
     * @return array{float, float}
     */
    public function focalPoint(): array
    {
        return [$this->focalX, $this->focalY];
    }

    public function updatedAt(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->updatedAt->format('Y-m-d H:i:s'), new DateTimeZone('UTC'));
    }

    private static function keyOf(string $id, int $version, FileInfo $file): string
    {
        return \sprintf('originals/%s/%d/%s', $id, $version, $file->safeFilename());
    }

    private function text(?string $text, int $length, string $what): ?string
    {
        $text = null === $text ? null : trim($text);

        if (null !== $text && mb_strlen($text) > $length) {
            throw InvalidMedia::because(\sprintf('The %s of a medium is %d characters or fewer.', $what, $length));
        }

        return '' === $text ? null : $text;
    }

    private static function utc(DateTimeImmutable $at): DateTimeImmutable
    {
        return $at->setTimezone(new DateTimeZone('UTC'));
    }
}
